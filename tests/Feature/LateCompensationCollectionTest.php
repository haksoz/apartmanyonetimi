<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAllocationInstruction;
use App\Services\LateCompensationCalculator;
use App\Services\LateCompensationEnrollment;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateCompensationCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_payment_resumes_accrual_on_only_the_remaining_principal(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $this->getJson(route('dues.payment.create', [$due, 'payment_date' => '2026-11-15']))
            ->assertOk()->assertJsonPath('amount', '512.50');
        $this->post(route('dues.payment.store', $due), $this->input($cash, '500.00'))->assertSessionHasNoErrors();
        $allocation = PaymentAllocation::sole();
        $this->assertSame('487.50', $allocation->principal_amount);
        $this->assertSame('12.50', $allocation->late_compensation_amount);
        $this->assertSame('12.50', $due->fresh()->remaining_amount);
        $this->assertSame('500.00', $due->fresh()->amount);
        $this->assertSame('manual', $allocation->instruction->mode);
        $this->assertSame('500.00', $allocation->instruction->items()->sole()->requested_amount);

        $calculator = app(LateCompensationCalculator::class);
        $this->assertSame(0, $calculator->balance($due->fresh(), '2026-11-15')['remaining_compensation']);
        $this->assertSame(6, $calculator->balance($due->fresh(), '2026-11-16')['remaining_compensation']);
    }

    public function test_full_payment_closes_ledger_with_one_payment_and_one_cash_entry(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '512.50'))->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $allocation = $payment->allocations()->sole();
        $this->assertSame('500.00', $allocation->principal_amount);
        $this->assertSame('12.50', $allocation->late_compensation_amount);
        $this->assertSame('12.50', $allocation->lateCompensationTransaction->amount);
        $this->assertSame('debit', $allocation->lateCompensationTransaction->type);
        $this->assertSame('512.50', $payment->cashTransactions()->sole()->amount);
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseCount('dues', 1);
        $this->assertSame('paid', $due->fresh()->status);
        $this->assertSame(0.0, $account->fresh()->ledger_balance);
        $this->assertSame(1250, app(LateCompensationCalculator::class)->balance($due, '2026-11-27')['accrued_compensation']);
    }

    public function test_same_day_payments_do_not_reaccrue_and_compensation_only_does_not_reduce_principal(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        foreach (['5.00', '10.00'] as $amount) {
            $this->post(route('dues.payment.store', $due), $this->input($cash, $amount))->assertSessionHasNoErrors();
        }
        $parts = PaymentAllocation::orderBy('id')->get();
        $this->assertSame(['5.00', '7.50'], $parts->pluck('late_compensation_amount')->all());
        $this->assertSame(['0.00', '2.50'], $parts->pluck('principal_amount')->all());
        $this->assertSame('497.50', $due->fresh()->remaining_amount);
    }

    public function test_rate_changes_pauses_start_dates_and_legacy_allocations_follow_the_payment_date(): void
    {
        [$apartment, $account] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $due->update(['late_compensation_starts_on' => '2026-11-12']);
        foreach ([['2026-11-13', false, 15], ['2026-11-15', true, 6]] as [$date, $active, $rate]) {
            $apartment->lateCompensationPolicies()->create(['effective_from' => $date, 'is_active' => $active, 'monthly_rate' => $rate]);
        }
        $legacy = Payment::create(['apartment_id' => $apartment->id, 'account_id' => $account->id,
            'amount' => 100, 'unallocated_amount' => 0, 'payment_date' => '2026-11-10']);
        $legacy->allocations()->create(['due_id' => $due->id, 'amount' => 100]);
        $due->update(['remaining_amount' => 400]);

        $calculator = app(LateCompensationCalculator::class);
        $this->assertSame(0, $calculator->balance($due, '2026-11-09')['accrued_compensation']);
        $this->assertSame(0, $calculator->balance($due, '2026-11-10')['accrued_compensation']);
        $this->assertSame(200, $calculator->balance($due, '2026-11-14')['accrued_compensation']);
        $this->assertSame(360, $calculator->balance($due, '2026-11-16')['accrued_compensation']);
    }

    public function test_on_time_payments_have_no_compensation_and_sub_cent_accrual_is_rounded_once(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '100.00', '2026-11-09'))->assertSessionHasNoErrors();
        $this->post(route('dues.payment.store', $due), $this->input($cash, '399.00', '2026-11-10'))->assertSessionHasNoErrors();
        $this->assertSame(['0.00', '0.00'], PaymentAllocation::orderBy('id')->pluck('late_compensation_amount')->all());
        $this->assertDatabaseCount('account_transactions', 3);
        $this->assertSame('1.00', $due->fresh()->remaining_amount);
        $calculator = app(LateCompensationCalculator::class);
        $this->assertSame(1, $calculator->balance($due, '2026-11-11')['accrued_compensation']);
        $this->assertSame(1, $calculator->balance($due, '2026-11-12')['accrued_compensation']);
    }

    public function test_fifo_uses_vade_order_and_does_not_allocate_to_debt_created_after_payment(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $older = $this->debt($apartment, $account);
        $next = $this->debt($apartment, $account, false, '2026-11-11');
        $future = $this->debt($apartment, $account, true, '2026-11-08', '2026-11-16');

        $this->post(route('payments.store'), $this->input($cash, '545.00') + [
            'account_id' => $account->id, 'action' => 'auto_allocate',
        ])->assertSessionHasNoErrors();

        $parts = PaymentAllocation::orderBy('id')->get();
        $this->assertSame([$older->id, $next->id], $parts->pluck('due_id')->all());
        $this->assertSame(['512.50', '32.50'], $parts->pluck('amount')->all());
        $this->assertSame('0.00', $parts[1]->late_compensation_amount);
        $this->assertSame('fifo', PaymentAllocationInstruction::sole()->mode);
        $this->assertSame('500.00', $future->fresh()->remaining_amount);
    }

    public function test_manual_combined_failure_rolls_back_every_record_and_rejects_foreign_targets(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $other = $this->debt($apartment, $account, false);
        $this->postJson(route('payments.store'), $this->input($cash, '601.00') + [
            'account_id' => $account->id, 'action' => 'fifo_popup',
            'allocations' => [['due_id' => $due->id, 'amount' => 100], ['due_id' => $other->id, 'amount' => 501]],
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations');

        $foreign = Apartment::factory()->create();
        $foreignAccount = $foreign->accounts()->create(['type' => 'owner', 'name' => 'Diğer']);
        $foreignDue = $this->debt($foreign, $foreignAccount);
        $this->postJson(route('payments.store'), $this->input($cash, '100.00') + [
            'account_id' => $account->id, 'action' => 'fifo_popup',
            'allocations' => [['due_id' => $foreignDue->id, 'amount' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('allocations.0.due_id');

        foreach (['payments', 'cash_transactions', 'payment_allocations', 'payment_allocation_instructions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('account_transactions', 3);
        $this->assertSame('500.00', $due->fresh()->remaining_amount);
    }

    public function test_late_allocations_use_original_payment_date_and_financial_edits_replay(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $input = $this->input($cash, '100.00') + ['account_id' => $account->id, 'action' => 'save'];
        $this->post(route('payments.store'), $input)->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $this->post(route('payments.allocations.store', $payment), ['allocations' => [['due_id' => $due->id, 'amount' => 100]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('12.50', PaymentAllocation::sole()->late_compensation_amount);

        $this->post(route('dues.payment.store', $due), $this->input($cash, '25.00', '2026-11-14'))
            ->assertSessionHasNoErrors();
        $this->assertSame('387.43', $due->fresh()->remaining_amount);
        $this->put(route('payments.update', $payment), $this->input($cash, '80.00', '2026-11-16'))
            ->assertSessionHasNoErrors();
        $this->assertSame('80.00', $payment->cashTransactions()->sole()->amount);
        $this->assertSame('2026-11-16', $payment->fresh()->payment_date->toDateString());
        $this->delete(route('cash.destroy', $payment->cashTransactions()->sole()))->assertSessionHasErrors('allocations');
        $this->delete(route('payments.destroy', $payment))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, CashTransaction::count());
        $this->assertSame('485.00', $due->fresh()->remaining_amount);
    }

    public function test_six_month_old_payment_moves_fifo_and_replay_is_repeatable(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $first = $this->debt($apartment, $account, true, '2026-05-10', '2026-05-01');
        $second = $this->debt($apartment, $account, true, '2026-05-11', '2026-05-01');
        $this->post(route('payments.store'), $this->input($cash, '500.00', '2026-06-15') + [
            'account_id' => $account->id, 'action' => 'auto_allocate',
        ])->assertSessionHasNoErrors();
        $fifo = Payment::sole();
        $cashId = $fifo->cashTransactions()->sole()->id;
        $this->assertSame($first->id, $fifo->allocations()->sole()->due_id);
        $this->getJson(route('payments.preview-allocations', [
            'account_id' => $account->id, 'payment_date' => '2026-05-20', 'amount' => 500,
        ]))->assertJsonPath('dues.0.id', $first->id);
        $this->post(route('dues.payment.store', $first), $this->input($cash, '500.00', '2026-05-20'))
            ->assertSessionHasNoErrors();
        $this->assertSame($second->id, $fifo->allocations()->sole()->due_id);
        $this->assertSame($cashId, $fifo->cashTransactions()->sole()->id);
        $this->assertSame('500.00', $fifo->fresh()->amount);
        $this->assertSame(0.0, $account->fresh()->ledger_balance);
        app(\App\Services\DueCollectionReplay::class)->run($apartment->id, $account->id, '2026-05-20');
        $this->assertSame($second->id, $fifo->allocations()->sole()->due_id);
        $this->assertSame($cashId, $fifo->cashTransactions()->sole()->id);
        $this->assertDatabaseCount('payment_allocations', 2);
        $this->assertDatabaseCount('account_transactions', 4);
    }

    public function test_manual_target_is_capped_and_removal_does_not_change_gross_or_cash(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $other = $this->debt($apartment, $account);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '500.00'))->assertSessionHasNoErrors();
        $later = Payment::sole();
        $cashId = $later->cashTransactions()->sole()->id;
        $this->post(route('dues.payment.store', $due), $this->input($cash, '250.00', '2026-11-10'))->assertSessionHasNoErrors();
        $kept = $later->allocations()->sole();
        $this->assertSame($due->id, $kept->due_id);
        $this->assertSame('256.25', $kept->amount);
        $this->assertSame('243.75', $later->fresh()->unallocated_amount);
        $this->assertSame('500.00', $other->fresh()->remaining_amount);
        $this->delete(route('payments.allocations.destroy', [$later, $kept]))->assertSessionHasNoErrors();
        $this->assertSame('500.00', $later->fresh()->amount);
        $this->assertSame('500.00', $later->fresh()->unallocated_amount);
        $this->assertSame($cashId, $later->cashTransactions()->sole()->id);
        $this->assertSame('250.00', $due->fresh()->remaining_amount);
    }

    public function test_bulk_collection_uses_one_payment_and_quotes_each_debt_on_the_payment_date(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $first = $this->debt($apartment, $account);
        $second = $this->debt($apartment, $account, false);
        $this->get(route('accounts.show', $account))->assertOk()->assertSee('data-late-compensation="1"', false);
        $this->getJson(route('payments.preview-allocations', [
            'account_id' => $account->id, 'amount' => '1012.50', 'payment_date' => '2026-11-15',
        ]))->assertOk()->assertJsonPath('dues.0.remaining_amount', 512.5);

        $input = ['due_ids' => [$first->id, $second->id], 'cash_box_id' => $cash->id, 'payment_date' => '2026-11-15'];
        $this->post(route('accounts.dues.bulk-pay', $account), $input + ['expected_amount' => '1000.00'])
            ->assertSessionHasErrors('allocations');
        $this->assertDatabaseCount('payments', 0);
        $this->post(route('accounts.dues.bulk-pay', $account), $input + ['expected_amount' => '1012.50'])->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame('1012.50', $payment->cashTransactions()->sole()->amount);
        $this->assertSame(['12.50', '0.00'], $payment->allocations()->orderBy('id')->pluck('late_compensation_amount')->all());
        $this->assertSame('0.00', $payment->unallocated_amount);
        $this->assertSame(0.0, $account->fresh()->ledger_balance);
    }

    public function test_manual_distribution_consumes_multiple_payments_in_payment_date_order(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        foreach ([['400.00', '2026-11-15'], ['100.00', '2026-11-14']] as [$amount, $date]) {
            $this->post(route('payments.store'), $this->input($cash, $amount, $date) + [
                'account_id' => $account->id, 'action' => 'save',
            ])->assertSessionHasNoErrors();
        }
        $ids = Payment::orderBy('id')->pluck('id');
        $this->get(route('accounts.payments.multi-allocate', ['account' => $account, 'payment_ids' => $ids->implode(',')]))
            ->assertOk()->assertSee('Önizle');
        $entries = [['due_id' => $due->id, 'amount' => 500]];
        $preview = $this->postJson(route('accounts.payments.multi-allocate.preview', $account), [
            'payment_ids' => $ids->all(), 'allocations' => $entries,
        ])->assertOk();
        $preview->assertJsonPath('payments.0.principal', 9000)
            ->assertJsonPath('payments.0.compensation', 1000)
            ->assertJsonPath('payments.1.principal', 39795)
            ->assertJsonPath('payments.1.compensation', 205)
            ->assertJsonPath('payments.1.unallocated', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('account_transactions', 3);
        $this->post(route('accounts.payments.multi-allocate.store', $account), [
            'payment_ids' => $ids->implode(','), 'allocations' => $entries, 'preview_hash' => $preview->json('hash'),
        ])->assertSessionHasNoErrors();

        $rows = PaymentAllocation::orderBy('id')->get();
        $this->assertSame([$ids[1], $ids[0]], $rows->pluck('payment_id')->all());
        $this->assertSame(['10.00', '2.05'], $rows->pluck('late_compensation_amount')->all());
        $this->assertSame('12.05', $due->fresh()->remaining_amount);
        $this->assertSame(1, PaymentAllocationInstruction::all()->pluck('operation_group')->unique()->count());
    }

    public function test_full_apartment_wipe_removes_linked_debits_before_allocations(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '512.50'))->assertSessionHasNoErrors();
        $foreign = Apartment::factory()->create();
        $other = $foreign->accounts()->create(['type' => 'owner', 'name' => 'Other']);
        $this->debt($foreign, $other);

        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Support\ApartmentReset\ApartmentOperationalData::class)->wipe($apartment));

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('payment_allocation_instructions', 0);
        $this->assertSame($foreign->id, AccountTransaction::sole()->apartment_id);
    }

    public function test_fifo_allocation_removal_keeps_the_remainder_as_fifo_without_spending_the_released_amount(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $first = $this->debt($apartment, $account, true, '2026-05-10', '2026-05-01');
        $second = $this->debt($apartment, $account, true, '2026-05-11', '2026-05-01');
        $this->post(route('payments.store'), $this->input($cash, '750.00', '2026-06-15') + [
            'account_id' => $account->id, 'action' => 'auto_allocate',
        ])->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $id = $payment->allocations()->where('due_id', $first->id)->sole()->id;
        $this->delete(route('payments.allocations.bulk-destroy', $payment), ['allocation_ids' => [$id]])
            ->assertSessionHasNoErrors();
        $this->assertSame('fifo', $payment->allocationInstructions()->sole()->mode);
        $this->assertSame('500.00', $payment->fresh()->unallocated_amount);
        $this->assertSame($second->id, $payment->allocations()->sole()->due_id);
        $this->assertSame('750.00', $payment->cashTransactions()->sole()->amount);
    }

    public function test_replay_respects_rate_changes_and_passive_periods(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        foreach ([['2026-11-13', false, 15], ['2026-11-15', true, 6]] as [$date, $active, $rate]) {
            $apartment->lateCompensationPolicies()->create(['effective_from' => $date, 'is_active' => $active, 'monthly_rate' => $rate]);
        }
        $this->post(route('dues.payment.store', $due), $this->input($cash, '100.00', '2026-11-16'))->assertSessionHasNoErrors();
        $later = Payment::sole();
        $this->assertSame('7.00', $later->allocations()->sole()->late_compensation_amount);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '100.00', '2026-11-10'))->assertSessionHasNoErrors();
        $this->assertSame('5.60', $later->allocations()->sole()->late_compensation_amount);
        $this->assertSame('100.00', $later->cashTransactions()->sole()->amount);
    }

    public function test_debt_and_linked_cash_corrections_replay_at_the_new_principal_vade_and_date(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $category = \App\Models\Category::create([
            'apartment_id' => $apartment->id, 'name' => 'General', 'allow_aidat' => true, 'is_active' => true,
        ]);
        $due->update(['category_id' => $category->id, 'created_at_manual' => '2026-11-01']);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '500.00'))->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $this->patch(route('dues.update', $due), [
            'amount' => '450.00', 'due_date' => '2026-11-12', 'created_at_manual' => '2026-11-01',
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('6.75', $payment->allocations()->sole()->late_compensation_amount);
        $this->assertSame('43.25', $payment->fresh()->unallocated_amount);

        $this->put(route('cash.update', $payment->cashTransactions()->sole()), [
            'cash_box_id' => $cash->id, 'account_id' => $account->id, 'amount' => '456.00',
            'description' => 'Cash correction', 'transaction_date' => '2026-11-14', 'type' => 'income', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('4.50', $payment->allocations()->sole()->late_compensation_amount);
        $this->assertSame('456.00', $payment->fresh()->amount);
        $this->assertSame('456.00', $payment->cashTransactions()->sole()->amount);
        $this->assertSame('1.50', $payment->fresh()->unallocated_amount);
    }

    public function test_transfer_preserves_unpaid_accrual_and_excludes_debt_from_earlier_target_fifo(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $unit = \App\Models\Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account->update(['unit_id' => $unit->id]);
        $target = $apartment->accounts()->create(['type' => 'owner', 'name' => 'Target', 'is_active' => true, 'unit_id' => $unit->id]);
        $due = $this->debt($apartment, $account);
        $due->update(['unit_id' => $unit->id, 'late_compensation_starts_on' => '2026-11-12']);
        $calculator = app(LateCompensationCalculator::class);
        $before = $calculator->balance($due, '2026-11-20');
        $this->post(route('dues.transfer', $due), ['target_account_id' => $target->id])->assertRedirect(route('accounts.show', $account));
        $this->assertSame($target->id, $due->fresh()->account_id);
        $this->assertSame('2026-11-20', $due->fresh()->collection_account_since->toDateString());
        $this->assertSame($before, $calculator->balance($due->fresh(), '2026-11-20'));

        $this->post(route('payments.store'), $this->input($cash, '100.00', '2026-11-19') + [
            'account_id' => $target->id, 'action' => 'auto_allocate',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertSame('100.00', Payment::sole()->unallocated_amount);
    }

    public function test_first_activation_previews_trusted_open_debts_and_keeps_the_enrollment_fixed(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $apartment->lateCompensationPolicies()->delete();
        $apartment->update(['late_compensation_starts_on' => null]);
        $eligible = $this->debt($apartment, $account, false);
        $eligible->update(['due_batch_id' => DueBatch::create([
            'apartment_id' => $apartment->id, 'source_type' => DueBatch::SOURCE_MANUAL,
            'due_type' => 'aidat', 'assessment_date' => '2026-11-01',
        ])->id]);
        $unknown = $this->debt($apartment, $account, false);
        $imported = $this->debt($apartment, $account, false);
        $imported->update(['is_imported' => true, 'created_at_manual' => null]);
        $closed = $this->debt($apartment, $account, false);
        $closed->update(['production_source' => 'individual']);
        $this->post(route('dues.payment.store', $eligible), $this->input($cash, '100.00', '2026-11-12'))->assertSessionHasNoErrors();
        $this->post(route('dues.payment.store', $closed), $this->input($cash, '500.00', '2026-11-12'))->assertSessionHasNoErrors();

        $input = [
            'late_compensation' => ['is_active' => 1, 'monthly_rate' => 15, 'starts_on' => '2026-11-20', 'include_open' => 1],
        ];
        $enrollment = app(LateCompensationEnrollment::class);
        config(['late-compensation.excluded_due_types' => ['individual' => ['aidat']]]);
        $restricted = collect($enrollment->preview($apartment, $input['late_compensation'])['rows'])->keyBy('id');
        $this->assertNull($restricted[$eligible->id]['reason']);
        $this->assertSame('Bu borç türünde kaynak kısıtlaması var ve üretim kaynağı doğrulanamıyor.', $restricted[$unknown->id]['reason']);
        config(['late-compensation.excluded_due_types' => ['manual_batch' => ['*']]]);
        $restricted = collect($enrollment->preview($apartment, $input['late_compensation'])['rows'])->keyBy('id');
        $this->assertSame('Merkezi uygulama kapsamı dışında.', $restricted[$eligible->id]['reason']);
        config(['late-compensation.excluded_due_types' => ['individual' => ['malik_payi']]]);
        $settings = route('apartments.index');
        $update = route('apartments.late-compensation.update', $apartment);
        $this->from($settings)->put($update, $input)->assertSessionHasErrors('late_compensation.include_open');
        $this->from($settings)->put($update, $input + ['preview_late_compensation' => 1])->assertRedirect($settings);
        $preview = session('late_compensation_enrollment.preview.rows');
        $this->assertSame([[$eligible->id, '400.00'], [$unknown->id, '500.00']], collect($preview)->whereNull('reason')->map(fn ($row) => [$row['id'], $row['principal']])->values()->all());
        $this->assertSame('İçe aktarılan borcun ana para geçmişi doğrulanamıyor.', collect($preview)->keyBy('id')[$imported->id]['reason']);
        $this->put($update, $input)->assertSessionHasNoErrors();
        $this->assertTrue($eligible->fresh()->late_compensation_applies);
        $this->assertTrue($unknown->fresh()->late_compensation_applies);
        $this->assertNull($eligible->fresh()->production_source);
        $this->assertNull($unknown->fresh()->production_source);
        $this->assertFalse($imported->fresh()->late_compensation_applies);
        $this->assertFalse($closed->fresh()->late_compensation_applies);

        $this->post(route('dues.payment.store', $eligible), $this->input($cash, '50.00', '2026-11-13'))->assertSessionHasNoErrors();
        $this->assertSame(175, app(LateCompensationCalculator::class)->balance($eligible->fresh(), '2026-11-20')['accrued_compensation']);
        $this->assertSame('2026-11-20', $eligible->fresh()->late_compensation_starts_on->toDateString());
    }

    public function test_failed_replay_restores_brutto_cash_allocations_and_linked_debit(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $due = $this->debt($apartment, $account);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '100.00'))->assertSessionHasNoErrors();
        $payment = Payment::sole();
        $allocation = $payment->allocations()->sole();
        $id = $allocation->lateCompensationTransaction->id;
        $due->update(['remaining_amount' => '411.00']);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '25.00', '2026-11-14'))
            ->assertSessionHasErrors('allocations');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertSame($id, $payment->allocations()->sole()->lateCompensationTransaction->id);
        $this->assertSame('411.00', $due->fresh()->remaining_amount);
    }

    public function test_current_debt_screen_and_exports_distinguish_payable_from_booked_balance(): void
    {
        [$apartment, $account] = $this->setUpCollection();
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->grantPaidFeatures($apartment);
        $due = $this->debt($apartment, $account);
        $this->get(route('reports.debt-list'))->assertOk()->assertSee('12,50')->assertSee('512,50');
        $this->assertSame(-500.0, $account->fresh()->ledger_balance);
        $this->assertDatabaseCount('account_transactions', 1);

        $excel = $this->get(route('reports.debt-list.export', ['type' => 'excel']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'aidatcep-report-');
        try {
            file_put_contents($path, $excel->streamedContent());
            $sheet = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path)->getActiveSheet();
            $this->assertEquals(500, $sheet->getCell('C4')->getValue());
            $this->assertEquals(12.5, $sheet->getCell('D4')->getValue());
            $this->assertEquals(512.5, $sheet->getCell('E4')->getValue());
        } finally {
            unlink($path);
        }

        $this->app->instance(\App\Support\PdfDownload::class, new class extends \App\Support\PdfDownload {
            public function response(string $view, array $data, string $filename, bool $landscape = false): \Symfony\Component\HttpFoundation\Response
            {
                return response()->view($view, $data);
            }
        });
        $this->get(route('reports.debt-list.export', ['type' => 'pdf']))->assertOk()->assertSee('12,50')->assertSee('512,50');
        $this->assertSame('500.00', $due->fresh()->remaining_amount);
    }

    public function test_period_board_and_cash_receipts_use_their_own_cutoffs_after_replay(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $this->grantPaidFeatures($apartment);
        $apartment->update(['late_compensation_starts_on' => '2026-10-01']);
        $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-10-01', 'is_active' => true, 'monthly_rate' => 15,
        ]);
        $unit = \App\Models\Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1', 'owner_account_id' => $account->id]);
        $account->update(['unit_id' => $unit->id]);
        $due = $this->debt($apartment, $account, true, '2026-10-26', '2026-10-01');
        $due->update(['unit_id' => $unit->id, 'created_at_manual' => '2026-10-01']);
        $this->post(route('dues.payment.store', $due), $this->input($cash, '550.00'))->assertSessionHasNoErrors();
        $october = $this->get(route('reports.monthly-board', ['month' => '2026-10']))->assertOk();
        $october->assertViewHas('balanceDate', '2026-10-31');
        $this->assertEquals(500, $october->viewData('accountData')[$account->id]['remaining']);
        $this->assertEquals(12.5, $october->viewData('accountData')[$account->id]['compensation']);
        $october->assertSee('512,50');

        $this->post(route('dues.payment.store', $due), $this->input($cash, '100.00', '2026-10-26'))->assertSessionHasNoErrors();
        $report = $this->get(route('reports.income-expense', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))->assertOk();
        $this->assertEquals(400, $report->viewData('aidatIncome'));
        $this->assertEquals(40, $report->viewData('compensationIncome'));
        $this->assertEquals(110, $report->viewData('otherIncome'));
        $this->assertEquals(550, $report->viewData('totalIncome'));
        $this->assertEquals(650, (float) CashTransaction::all()->sum('amount'));
        $cashBox = $this->get(route('cash-boxes.show', $cash))->assertOk();
        $this->assertEquals(650, $cashBox->viewData('income'));
        $this->assertEquals(40, $cashBox->viewData('compensationIncome'));
    }

    public function test_replay_preview_is_read_only_and_agrees_after_a_later_fifo_is_redistributed(): void
    {
        [$apartment, $account, $cash] = $this->setUpCollection();
        $first = $this->debt($apartment, $account, true, '2026-05-10', '2026-05-01');
        $next = $this->debt($apartment, $account, true, '2026-05-11', '2026-05-01');
        $this->post(route('payments.store'), $this->input($cash, '500.00', '2026-06-15') + [
            'account_id' => $account->id, 'action' => 'auto_allocate',
        ])->assertSessionHasNoErrors();
        $fifo = Payment::sole();
        foreach ([['300.00', '2026-05-20'], ['200.00', '2026-05-21']] as [$amount, $date]) {
            $this->post(route('payments.store'), $this->input($cash, $amount, $date) + [
                'account_id' => $account->id, 'action' => 'save',
            ])->assertSessionHasNoErrors();
        }
        $ids = Payment::whereKeyNot($fifo->id)->orderBy('id')->pluck('id');
        $rows = [['due_id' => $first->id, 'amount' => '500.00']];
        $preview = $this->postJson(route('accounts.payments.multi-allocate.preview', $account), [
            'payment_ids' => $ids->all(), 'allocations' => $rows,
        ])->assertOk()->assertJsonPath('payments.0.principal', 30000)->assertJsonPath('payments.1.principal', 20000);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertSame($first->id, $fifo->allocations()->sole()->due_id);
        $input = ['payment_ids' => $ids->implode(','), 'preview_hash' => $preview->json('hash')];
        $this->post(route('accounts.payments.multi-allocate.store', $account), $input + [
            'allocations' => [['due_id' => $first->id, 'amount' => '499.99']],
        ])->assertSessionHasErrors('allocations');
        $this->post(route('accounts.payments.multi-allocate.store', $account), $input + ['allocations' => $rows])->assertSessionHasNoErrors();
        $this->assertSame($next->id, $fifo->allocations()->sole()->due_id);
        $this->assertSame(0.0, $account->fresh()->ledger_balance);
    }

    public function test_batch_snapshot_uses_fixed_queries_and_passive_policy_keeps_old_accrual_visible(): void
    {
        [$apartment, $account] = $this->setUpCollection();
        $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-16', 'is_active' => false, 'monthly_rate' => 15,
        ]);
        $debts = collect(range(1, 40))->map(fn () => $this->debt($apartment, $account));
        $db = \Illuminate\Support\Facades\DB::connection();
        $db->enableQueryLog();
        $db->flushQueryLog();
        try {
            $balances = app(\App\Services\LateCompensationBalances::class)->forDues($apartment, $debts, '2026-11-20');
            $this->assertCount(40, $balances);
            $this->assertSame(50000, $balances->sum('remaining_compensation'));
            $this->assertLessThanOrEqual(3, count($db->getQueryLog()));
        } finally {
            $db->disableQueryLog();
        }
    }

    public function test_resident_portal_shows_own_payable_with_no_other_accounts_financial_data(): void
    {
        [$apartment, $account] = $this->setUpCollection();
        $resident = \App\Models\User::factory()->create(['role' => 'user']);
        $apartment->members()->attach($resident->id, ['role' => 'member']);
        $account->update(['user_id' => $resident->id]);
        $this->debt($apartment, $account);
        $other = $apartment->accounts()->create(['type' => 'owner', 'name' => 'Other account', 'is_active' => true]);
        $otherDebt = $this->debt($apartment, $other);
        $otherDebt->update(['description' => 'Private other debt', 'amount' => 1000, 'remaining_amount' => 1000]);
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $this->actingAs($resident)->get(route('residence.account', $account))
            ->assertOk()->assertSee('512,50')->assertDontSee('Private other debt')->assertDontSee('1.025,00');
        $this->get(route('residence.account', $other))->assertForbidden();
    }

    private function setUpCollection(): array
    {
        config(['late-compensation.preview' => true]);
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $user = \App\Models\User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['late_compensation_starts_on' => '2026-11-01']);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        \App\Models\Category::createDefaultsFor($apartment->id);
        $apartment->lateCompensationPolicies()->create(['effective_from' => '2026-11-01', 'is_active' => true, 'monthly_rate' => 15]);
        $account = $apartment->accounts()->create(['type' => 'owner', 'name' => 'Kat maliki', 'is_active' => true]);
        $cash = $apartment->cashBoxes()->create(['name' => 'Kasa', 'is_active' => true]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return [$apartment, $account, $cash];
    }

    private function debt(Apartment $apartment, Account $account, bool $eligible = true, string $vade = '2026-11-10', string $created = '2026-11-01'): Due
    {
        $due = Due::create([
            'apartment_id' => $apartment->id, 'account_id' => $account->id, 'amount' => 500,
            'remaining_amount' => 500, 'status' => 'unpaid', 'due_type' => 'aidat',
            'due_date' => $vade, 'late_compensation_applies' => $eligible,
        ]);
        $due->forceFill(['created_at' => $created])->save();
        $due->transactions()->create([
            'apartment_id' => $apartment->id, 'account_id' => $account->id, 'type' => 'debit',
            'amount' => 500, 'transaction_date' => $created,
        ]);

        return $due;
    }

    private function input(CashBox $cash, string $amount, string $date = '2026-11-15'): array
    {
        return ['cash_box_id' => $cash->id, 'amount' => $amount, 'payment_date' => $date];
    }
}
