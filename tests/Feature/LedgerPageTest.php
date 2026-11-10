<?php

namespace Tests\Feature;

use App\Models\{Account, AccountTransaction, Apartment, Due, Expense, Payment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerPageTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $tenant;
    private Account $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Birleşik Muhasebe', 'unit_count' => 1]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        $this->tenant = Account::create(['apartment_id' => $this->apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Kiracı']);
        $this->supplier = Account::create(['apartment_id' => $this->apartment->id,
            'type' => Account::TYPE_SUPPLIER, 'name' => 'Tedarikçi']);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id]);
    }

    private function payment(?Account $account, bool $posted = true, float $unallocated = 250): Payment
    {
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $account?->id,
            'amount' => 500, 'unallocated_amount' => $unallocated, 'payment_date' => '2026-11-10',
            'description' => 'Örnek ödeme']);
        if ($posted) {
            $payment->transactions()->create(['apartment_id' => $this->apartment->id, 'account_id' => $account?->id,
                'type' => $account?->type === Account::TYPE_SUPPLIER ? 'debit' : 'credit',
                'amount' => 500, 'transaction_date' => $payment->payment_date, 'description' => 'Örnek ödeme']);
        }
        return $payment;
    }

    public function test_unified_list_preserves_payment_details_and_does_not_duplicate_posted_payments(): void
    {
        $receipt = $this->payment($this->tenant);
        $outgoing = $this->payment($this->supplier);
        $orphan = $this->payment(null, false);
        $before = AccountTransaction::count();
        $screen = $this->get(route('ledger.index'))->assertOk()->assertSee('Muhasebe Hareketleri')
            ->assertSee('Tahsis bekleyen: 250,00 TL')->assertSee('Hesapsız')
            ->assertSee(route('payments.show', $receipt), false)
            ->assertSee(route('payments.show', $orphan), false)
            ->assertSee(route('payments.allocations.create', $receipt), false)
            ->assertSee(route('payments.supplier-allocations.create', $outgoing), false)
            ->assertDontSee('sidebar-text">Ödeme Hareketleri', false);
        $rows = $screen->viewData('transactions');
        $this->assertSame(3, $rows->total());
        $this->assertEqualsCanonicalizing([$receipt->id, $outgoing->id, $orphan->id], $rows->pluck('transactionable_id')->all());
        $this->assertSame($before, AccountTransaction::count());
    }

    public function test_filters_keep_receipts_outgoing_charges_and_unallocated_payments_separate(): void
    {
        $receipt = $this->payment($this->tenant);
        $outgoing = $this->payment($this->supplier, false, 0);
        $charge = AccountTransaction::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->tenant->id,
            'type' => 'debit', 'amount' => 600, 'transaction_date' => '2026-11-01', 'description' => 'Açılış borcu']);
        foreach (['receipts' => $receipt, 'payments' => $outgoing] as $kind => $payment) {
            $rows = $this->get(route('ledger.index', ['kind' => $kind]))->assertOk()->viewData('transactions');
            $this->assertSame(1, $rows->total());
            $this->assertSame($payment->id, $rows->first()->transactionable_id);
        }
        $rows = $this->get(route('ledger.index', ['kind' => 'charges']))->assertOk()->viewData('transactions');
        $this->assertSame([$charge->id], $rows->pluck('id')->all());
        $rows = $this->get(route('ledger.index', ['filter' => 'unallocated']))->assertOk()->viewData('transactions');
        $this->assertSame([$receipt->id], $rows->pluck('transactionable_id')->all());
        $this->get(route('ledger.index', ['kind' => 'payments']))->assertSee('Tam Tahsis');
    }

    public function test_orphan_filter_includes_null_and_hidden_accounts_with_or_without_entries(): void
    {
        $this->payment($this->tenant);
        $null = $this->payment(null, false);
        $hidden = $this->payment($this->apartment->getOrphanAccount());
        $this->payment(null, false, 0);
        $rows = $this->get(route('ledger.index', ['filter' => 'orphan']))->assertOk()->viewData('transactions');
        $this->assertEqualsCanonicalizing([$null->id, $hidden->id], $rows->pluck('transactionable_id')->all());
    }

    public function test_other_apartments_are_excluded_and_deleted_accounts_keep_their_history(): void
    {
        $payment = $this->payment($this->tenant);
        $this->tenant->delete();
        $other = Apartment::create(['user_id' => $this->apartment->user_id, 'name' => 'Other', 'unit_count' => 1]);
        Payment::create(['apartment_id' => $other->id, 'amount' => 9000, 'unallocated_amount' => 9000,
            'payment_date' => '2026-11-11', 'description' => 'Yabancı ödeme']);
        AccountTransaction::create(['apartment_id' => $other->id, 'account_id' => $this->supplier->id,
            'type' => 'debit', 'amount' => 9000, 'transaction_date' => '2026-11-11', 'description' => 'Yabancı hareket']);
        $screen = $this->get(route('ledger.index'))->assertOk()->assertSee('Kiracı')->assertDontSee('Yabancı ödeme')->assertDontSee('Yabancı hareket');
        $this->assertSame(1, $screen->viewData('transactions')->total());
        $this->assertSame($payment->id, $screen->viewData('transactions')->first()->transactionable_id);
    }

    public function test_legacy_list_redirects_and_pagination_preserve_filters(): void
    {
        $this->get(route('payments.index', ['filter' => 'orphan', 'page' => 2]))
            ->assertRedirect(route('ledger.index', ['filter' => 'orphan', 'page' => 2]));
        for ($index = 0; $index < 27; $index++) $this->payment($this->tenant, $index % 2 === 0);
        $first = $this->get(route('ledger.index', ['kind' => 'receipts', 'filter' => 'unallocated']))->assertOk()->viewData('transactions');
        $second = $this->get(route('ledger.index', ['kind' => 'receipts', 'filter' => 'unallocated', 'page' => 2]))->assertOk()->viewData('transactions');
        $this->assertSame(27, $first->total());
        $this->assertCount(25, $first);
        $this->assertCount(2, $second);
        $this->assertEmpty(array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->assertStringContainsString('filter=unallocated', $first->nextPageUrl());
        $this->getJson(route('ledger.index', ['kind' => 'invalid']))->assertUnprocessable();
    }

    public function test_document_links_keep_payment_due_and_expense_details_accessible(): void
    {
        $payment = $this->payment($this->tenant);
        $due = Due::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->tenant->id,
            'unit_id' => $this->tenant->unit_id, 'due_type' => 'aidat', 'period' => '2026-11',
            'amount' => 500, 'remaining_amount' => 500, 'due_date' => '2026-11-01', 'status' => 'unpaid']);
        $expense = Expense::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'description' => 'Bakım', 'category' => 'Bakım', 'amount' => 500,
            'remaining_amount' => 500, 'expense_date' => '2026-11-01']);
        foreach ([$due, $expense] as $document) {
            AccountTransaction::create(['apartment_id' => $this->apartment->id, 'account_id' => $document->account_id,
                'transactionable_type' => $document->getMorphClass(), 'transactionable_id' => $document->id,
                'type' => 'debit', 'amount' => 500, 'transaction_date' => '2026-11-01']);
        }
        $this->get(route('ledger.index'))->assertOk()
            ->assertSee(route('payments.show', $payment), false)
            ->assertSee(route('dues.show', $due), false)
            ->assertSee(route('expenses.show', $expense), false);
        $this->get(route('payments.show', $payment))->assertOk()->assertSee('Tahsis Et')
            ->assertSee('Kalan Bakiye')->assertSee('href="'.route('ledger.index').'"', false);
    }

    public function test_legacy_entries_without_apartment_id_remain_visible_only_to_their_account_apartment(): void
    {
        $entry = AccountTransaction::create(['account_id' => $this->tenant->id,
            'type' => 'debit', 'amount' => 100, 'transaction_date' => '2026-11-01']);
        $rows = $this->get(route('ledger.index'))->assertOk()->viewData('transactions');
        $this->assertSame([$entry->id], $rows->pluck('id')->all());
    }
}
