<?php

namespace Tests\Feature\Admin;

use App\Models\OrderInvoice;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderInvoiceXmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_xml_upload_preview_confirm_and_download_preserve_the_original_order_totals(): void
    {
        $invoice = $this->tracking();
        $original = $invoice->snapshot;
        $xml = $this->xml();
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Satış XML gir');
        $this->get(route('admin.order-invoices.xml.create'))->assertOk();
        $this->post(route('admin.order-invoices.xml.upload'), [
            'xml_file' => UploadedFile::fake()->createWithContent('fatura.xml', $xml),
        ])->assertRedirect(route('admin.order-invoices.xml.preview'));
        $this->get(route('admin.order-invoices.xml.preview'))->assertOk()
            ->assertSee('ATM2026000000001')->assertSee($invoice->trackingNumber())
            ->assertViewHas('candidates', fn ($rows) => $rows->modelKeys() === [$invoice->id]);
        $this->confirm($invoice)->assertRedirect(route('admin.order-invoices.show', $invoice));
        $invoice->refresh();
        $this->assertSame('ATM2026000000001', $invoice->invoice_number);
        $this->assertSame('2026-11-19', $invoice->invoice_date->toDateString());
        $this->assertSame($original, $invoice->snapshot);
        $this->assertSame($xml, base64_decode($invoice->xml_content));
        $this->assertNotNull($invoice->xml_imported_by);
        $this->assertSame(120, $invoice->xml_details['amount']);
        $this->get(route('admin.order-invoices.show', $invoice))->assertOk()->assertSee('XML’i indir')->assertSee('ATM2026000000001');
        $this->get(route('admin.order-invoices.xml.download', $invoice))->assertOk()->assertContent($xml);
        $this->assertNull(session('order_invoice_xml_preview'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->upload($xml)->assertSessionHasErrors('xml_content');
    }

    public function test_abn_only_lists_multiple_orders_without_using_invoice_date_as_service_period(): void
    {
        $first = $this->tracking('SIP-26-000001');
        $second = $this->tracking('SIP-26-000002');
        $this->upload($this->xml('AidatCep Aylık Abonelik - ABN-26-123456'));
        $this->get(route('admin.order-invoices.xml.preview'))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->count() === 2);
        $this->confirm($second)->assertSessionHasNoErrors();
        $this->assertNull($first->fresh()->invoice_number);
        $this->assertNotNull($second->fresh()->invoice_number);
    }

    public function test_sip_never_falls_back_to_another_order_with_the_same_abn(): void
    {
        $invoice = $this->tracking('SIP-26-000001');
        $this->upload($this->xml('ABN-26-123456 - SIP-26-999999'));
        $this->get(route('admin.order-invoices.xml.preview'))->assertOk()
            ->assertSee('Eşleşen açık fatura takip kaydı bulunamadı.')
            ->assertViewHas('candidates', fn ($rows) => $rows->isEmpty());
        $this->confirm($invoice)->assertSessionHasErrors('invoice_id');
        $this->assertNull($invoice->fresh()->invoice_number);
    }

    public function test_wrong_recipient_or_subscription_cannot_be_selected_even_with_a_matching_sip(): void
    {
        $invoice = $this->tracking();
        $this->upload(str_replace('1234567890', '9999999999', $this->xml()));
        $this->confirm($invoice)->assertSessionHasErrors('invoice_id');
        $this->upload($this->xml('ABN-26-999999 - SIP-26-000001'));
        $this->confirm($invoice)->assertSessionHasErrors('invoice_id');
        $this->assertNull($invoice->fresh()->invoice_number);
    }

    public function test_amount_differences_require_a_reason_and_do_not_replace_system_totals(): void
    {
        $invoice = $this->tracking();
        $this->upload($this->xml(net: '101.00', tax: '20.20', gross: '121.20'));
        $this->confirm($invoice)->assertSessionHasErrors('difference_reason');
        $this->confirm($invoice, ['difference_reason' => '   '])->assertSessionHasErrors('difference_reason');
        $this->confirm($invoice, ['difference_reason' => 'Muhasebede tutar düzeltildi.'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertEquals(120, $invoice->snapshot['amount']);
        $this->assertEquals(121.2, $invoice->xml_details['amount']);
        $this->assertSame('Muhasebede tutar düzeltildi.', $invoice->xml_difference_reason);
    }

    public function test_xml_is_rechecked_if_the_tracking_record_changes_after_preview(): void
    {
        $invoice = $this->tracking();
        $this->upload($this->xml());
        $invoice->update(['invoice_number' => 'ALREADY-ISSUED']);
        $this->confirm($invoice)->assertSessionHasErrors('invoice_id');
        $this->assertSame('ALREADY-ISSUED', $invoice->fresh()->invoice_number);
        $this->assertNull($invoice->fresh()->xml_content);
    }

    public function test_invalid_xml_is_rejected_without_creating_an_import(): void
    {
        foreach ([
            '<broken>',
            '<Invoice/>',
            str_replace('TRY', 'USD', $this->xml()),
            str_replace('2026-11-19', '2026-02-30', $this->xml()),
            $this->xml('AidatCep Abonelik'),
            $this->xml('ABN-26-123456 - SIP-26-000001 - SIP-26-000002'),
            $this->xml(gross: '121.00'),
            str_replace('1234567890', '', $this->xml()),
            str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0"?><!DOCTYPE Invoice [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>', $this->xml()),
        ] as $xml) {
            $this->upload($xml)->assertSessionHasErrors('xml_content');
        }
        $this->assertDatabaseCount('order_invoices', 0);
    }

    public function test_old_preview_tokens_and_expired_sessions_cannot_apply_a_different_xml(): void
    {
        $invoice = $this->tracking();
        $this->upload($this->xml());
        $oldToken = session('order_invoice_xml_preview.token');
        $this->upload(str_replace('ATM2026000000001', 'ATM2026000000002', $this->xml()));
        $this->post(route('admin.order-invoices.xml.confirm'), ['preview_token' => $oldToken, 'invoice_id' => $invoice->id])
            ->assertRedirect(route('admin.order-invoices.xml.create'));
        $this->travel(61)->minutes();
        $this->get(route('admin.order-invoices.xml.preview'))->assertRedirect(route('admin.order-invoices.xml.create'));
        $this->assertNull($invoice->fresh()->invoice_number);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cancelling_discards_xml_without_changing_the_tracking_record(): void
    {
        $invoice = $this->tracking();
        $this->upload($this->xml());
        $this->post(route('admin.order-invoices.xml.cancel'))->assertRedirect(route('admin.orders.index'));
        $this->assertNull(session('order_invoice_xml_preview'));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull($invoice->fresh()->invoice_number);
    }

    public function test_uuid_cannot_be_reused_with_another_invoice_number(): void
    {
        $first = $this->tracking();
        $this->upload($this->xml());
        $this->confirm($first)->assertSessionHasNoErrors();
        $this->upload(str_replace('ATM2026000000001', 'ATM2026000000099', $this->xml()))->assertSessionHasErrors('xml_content');
    }

    public function test_utf16_xml_is_archived_without_changing_its_bytes(): void
    {
        $invoice = $this->tracking();
        $xml = "\xFF\xFE".mb_convert_encoding(str_replace('encoding="UTF-8"', 'encoding="UTF-16"', $this->xml()), 'UTF-16LE', 'UTF-8');
        $this->post(route('admin.order-invoices.xml.upload'), ['xml_file' => UploadedFile::fake()->createWithContent('fatura.xml', $xml)])
            ->assertSessionHasNoErrors();
        $this->confirm($invoice)->assertSessionHasNoErrors();
        $this->assertSame($xml, base64_decode($invoice->fresh()->xml_content));
    }

    public function test_non_admin_users_cannot_upload_match_or_download_xml(): void
    {
        $invoice = $this->tracking();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.order-invoices.xml.create'))->assertForbidden();
        $this->upload($this->xml())->assertForbidden();
        $this->get(route('admin.order-invoices.xml.preview'))->assertForbidden();
        $this->post(route('admin.order-invoices.xml.confirm'), ['invoice_id' => $invoice->id])->assertForbidden();
        $this->get(route('admin.order-invoices.xml.download', $invoice))->assertForbidden();
    }

    private function upload(string $xml)
    {
        return $this->from(route('admin.order-invoices.xml.create'))->post(route('admin.order-invoices.xml.upload'), ['xml_content' => $xml]);
    }

    private function confirm(OrderInvoice $invoice, array $extra = [])
    {
        return $this->from(route('admin.order-invoices.xml.preview'))->post(route('admin.order-invoices.xml.confirm'), [
            'invoice_id' => $invoice->id, 'preview_token' => session('order_invoice_xml_preview.token'), ...$extra,
        ]);
    }

    private function tracking(string $number = 'SIP-26-000001'): OrderInvoice
    {
        $order = UserSubscription::factory()->create(['order_number' => $number]);

        return OrderInvoice::create([
            'user_subscription_id' => $order->id, 'snapshot' => [
                'order_number' => $number, 'subscription_numbers' => ['ABN-26-123456'], 'payer_name' => 'Musteri',
                'billing' => ['billing_identity_number' => '1234567890', 'billing_legal_name' => 'Alici A.S.'],
                'lines' => [['description' => 'AidatCep Aylık Abonelik - ABN-26-123456 - '.$number, 'net_amount' => 100, 'tax_amount' => 20, 'tax_rate' => 20, 'amount' => 120]],
                'net_amount' => 100, 'tax_amount' => 20, 'amount' => 120,
            ],
        ]);
    }

    private function xml(string $description = 'AidatCep Aylık Abonelik - ABN-26-123456 - SIP-26-000001', string $net = '100.00', string $tax = '20.00', string $gross = '120.00'): string
    {
        $description = htmlspecialchars($description, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
<cbc:ID>ATM2026000000001</cbc:ID><cbc:UUID>8cf806bd-94c5-4a58-bd59-90456d67149e</cbc:UUID><cbc:IssueDate>2026-11-19</cbc:IssueDate><cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode><cbc:DocumentCurrencyCode>TRY</cbc:DocumentCurrencyCode>
<cac:AccountingCustomerParty><cac:Party><cac:PartyIdentification><cbc:ID schemeID="VKN">1234567890</cbc:ID></cac:PartyIdentification><cac:PartyName><cbc:Name>Alici A.S.</cbc:Name></cac:PartyName></cac:Party></cac:AccountingCustomerParty>
<cac:TaxTotal><cbc:TaxAmount currencyID="TRY">{$tax}</cbc:TaxAmount></cac:TaxTotal>
<cac:LegalMonetaryTotal><cbc:TaxExclusiveAmount currencyID="TRY">{$net}</cbc:TaxExclusiveAmount><cbc:TaxInclusiveAmount currencyID="TRY">{$gross}</cbc:TaxInclusiveAmount><cbc:PayableAmount currencyID="TRY">{$gross}</cbc:PayableAmount></cac:LegalMonetaryTotal>
<cac:InvoiceLine><cac:Item><cbc:Name>{$description}</cbc:Name></cac:Item></cac:InvoiceLine>
</Invoice>
XML;
    }
}
