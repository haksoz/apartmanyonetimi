<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\OrderInvoice;
use App\Models\SubscriptionItem;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_is_invoiced_with_frozen_details_and_atmaca_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->order();
        $this->pay($order, 120);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Faturala')->assertDontSee('name="invoice_date"', false)->assertDontSee('name="invoice_number"', false);
        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee('>Faturala</button>', false)
            ->assertSee('id="invoice-order-modal-'.$order->id.'"', false)
            ->assertSee(route('admin.orders.invoice.store', $order), false);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasNoErrors();
        $invoice = OrderInvoice::sole();
        $this->assertSame($admin->id, $invoice->created_by);
        $this->assertNull($invoice->invoice_number);
        $this->assertNull($invoice->invoice_date);
        $this->assertSame('FTN000001', $invoice->trackingNumber());
        $this->assertEquals(120, $invoice->snapshot['amount']);
        $this->assertEquals(100, $invoice->snapshot['net_amount']);
        $this->assertEquals(20, $invoice->snapshot['tax_amount']);
        $this->assertStringContainsString("\t1\t100.00\t20\t0", $invoice->atmacaText());
        $this->assertStringContainsString('AidatCep Aylık Abonelik - '.$order->subscription->subscription_no.' - '.$order->order_number, $invoice->atmacaText());
        $original = $invoice->atmacaText();
        $order->update(['billing_legal_name' => 'Sonradan Degisti', 'price' => 999]);
        $order->items()->update(['amount' => 999, 'apartment_name' => 'Yeni Ad']);
        $this->assertSame($original, $invoice->fresh()->atmacaText());
        $this->get(route('admin.order-invoices.show', $invoice))->assertOk()
            ->assertSee('FTN000001')->assertSee('XML bekleniyor')->assertSee('Orijinal Alici')->assertSee('Atmaca’ya kopyala')
            ->assertDontSee('Sonradan Degisti')->assertSee('100,00')->assertSee('120,00');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Fatura takibi')->assertDontSee('>Faturala</button>', false);
        $this->get(route('admin.orders.index'))->assertOk()
            ->assertSee(route('admin.order-invoices.show', $invoice), false)
            ->assertSee('Fatura takibi')->assertDontSee('>Faturala</button>', false);
    }

    public function test_partial_missing_and_deleted_payments_cannot_be_invoiced(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $order = $this->order();
        $this->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('>Faturala</button>', false);
        $this->get(route('admin.orders.index'))->assertOk()->assertDontSee('>Faturala</button>', false);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasErrors('invoicing');
        $payment = $this->pay($order, 119.99);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasErrors('invoicing');
        $payment->update(['amount' => 120]);
        $payment->delete();
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasErrors('invoicing');
        $this->assertDatabaseCount('order_invoices', 0);
    }

    public function test_duplicate_order_is_rejected_and_tracking_numbers_are_unique(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = $this->order();
        $this->pay($first, 120);
        $this->post(route('admin.orders.invoice.store', $first))->assertSessionHasNoErrors();
        $this->post(route('admin.orders.invoice.store', $first))->assertSessionHasErrors('invoicing');
        $second = $this->order();
        $this->pay($second, 120);
        $this->post(route('admin.orders.invoice.store', $second))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('order_invoices', 2);
        $this->assertCount(2, OrderInvoice::all()->map->trackingNumber()->unique());
    }

    public function test_permissions_are_enforced_and_real_invoice_fields_cannot_be_set_at_creation(): void
    {
        $order = $this->order();
        $this->pay($order, 120);
        $this->actingAs(User::factory()->create())->post(route('admin.orders.invoice.store', $order))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.orders.invoice.store', $order), ['invoice_number' => 'REAL-123', 'invoice_date' => '2026-11-19'])
            ->assertSessionHasNoErrors();
        $this->assertNull(OrderInvoice::sole()->invoice_number);
        $this->assertNull(OrderInvoice::sole()->invoice_date);
        $this->actingAs(User::factory()->create())->get(route('admin.order-invoices.show', OrderInvoice::sole()))->assertForbidden();
    }

    public function test_inconsistent_amounts_and_cancelled_orders_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $order = $this->order();
        $this->pay($order, 120);
        $order->items()->update(['amount' => 121]);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasErrors('invoicing');
        $order->items()->update(['amount' => 120]);
        $order->update(['status' => UserSubscription::STATUS_CANCELLED]);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasErrors('invoicing');
        $this->assertDatabaseCount('order_invoices', 0);
    }

    public function test_invoiced_payment_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $order = $this->order();
        $payment = $this->pay($order, 120);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasNoErrors();
        $this->delete(route('admin.orders.payments.destroy', [$order, $payment]), ['current_password' => 'password'])
            ->assertSessionHasErrors('order');
        $this->assertNotNull($payment->fresh());
        $this->assertNull($payment->fresh()->deleted_at);
    }

    public function test_multi_line_invoice_preserves_each_vat_rate_and_export_row(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $order = $this->order();
        $line = $order->items()->first()->replicate();
        $line->fill(['amount' => 110, 'net_amount' => 100, 'tax_amount' => 10, 'tax_rate' => 10, 'apartment_name' => "B\tApartman\n"])->save();
        $order->update(['price' => 230, 'net_amount' => 200, 'tax_amount' => 30]);
        $this->pay($order, 230);
        $this->post(route('admin.orders.invoice.store', $order))->assertSessionHasNoErrors();
        $invoice = OrderInvoice::sole();
        $rows = explode("\n", $invoice->atmacaText());
        $this->assertCount(2, $rows);
        $this->assertCount(5, explode("\t", $rows[1]));
        $this->assertStringContainsString("\t100.00\t10\t0", $rows[1]);
        $this->assertEquals(30, $invoice->snapshot['tax_amount']);
    }

    private function order(): UserSubscription
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create();
        $subscription = Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => Subscription::STATUS_ACTIVE,
            'subscription_no' => 'ABN-26-'.str_pad((string) $apartment->id, 6, '0', STR_PAD_LEFT),
        ]);
        $order = UserSubscription::factory()->create([
            'subscription_id' => $subscription->id,
            'period' => UserSubscription::PERIOD_MONTHLY,
            'user_id' => $user->id, 'status' => UserSubscription::STATUS_ACTIVE, 'is_active' => true,
            'price' => 120, 'net_amount' => 100, 'tax_rate' => 20, 'tax_amount' => 20,
            'billing_legal_name' => 'Orijinal Alici', 'billing_identity_number' => '1234567890',
        ]);
        $order->items()->create([
            'apartment_subscription_id' => $subscription->id,
            'apartment_id' => $apartment->id, 'apartment_name' => 'Orijinal Apartman',
            'plan' => SubscriptionItem::PLAN_PAID, 'status' => SubscriptionItem::STATUS_ACTIVE,
            'unit_count' => 20, 'band_label' => '15–30 daire', 'band_min_units' => 15, 'currency' => 'TRY', 'amount' => 120, 'net_amount' => 100, 'tax_rate' => 20, 'tax_amount' => 20,
        ]);

        return $order;
    }

    private function pay(UserSubscription $order, float $amount): SubscriptionPayment
    {
        return $order->payments()->create(['amount' => $amount, 'payment_date' => now(), 'payment_method' => 'havale']);
    }
}
