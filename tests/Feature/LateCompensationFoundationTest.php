<?php

namespace Tests\Feature;

use App\Enums\DueType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Payment;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateCompensationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_due_allocations_read_as_principal_and_policies_are_scoped(): void
    {
        [$apartment, $account, $payment] = $this->accountWithPayment();
        $due = $this->due($account);
        $allocation = $payment->allocations()->create(['due_id' => $due->id, 'amount' => '22.50']);

        $this->assertNull($apartment->fresh()->late_compensation_starts_on);
        $this->assertFalse($due->fresh()->late_compensation_applies);
        $this->assertNull($due->fresh()->production_source);
        $this->assertNull($allocation->fresh()->getRawOriginal('principal_amount'));
        $this->assertSame('22.50', $allocation->fresh()->principal_amount);
        $this->assertSame('0.00', $allocation->fresh()->late_compensation_amount);
        $this->assertNull($allocation->fresh()->allocation_instruction_id);
        $this->assertSame('100.00', $due->fresh()->remaining_amount);
        $this->assertSame('100.00', $payment->fresh()->unallocated_amount);

        $policy = $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-27',
            'monthly_rate' => '2.123456',
        ]);
        [$otherApartment] = $this->accountWithPayment();
        $otherApartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-27',
            'monthly_rate' => '3.000000',
            'is_active' => true,
        ]);
        $this->assertFalse($policy->fresh()->is_active);
        $this->assertSame('2.123456', $apartment->lateCompensationPolicies()->sole()->monthly_rate);
        $this->assertSame('3.000000', $otherApartment->lateCompensationPolicies()->sole()->monthly_rate);

        $this->expectException(UniqueConstraintViolationException::class);
        $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-27',
            'monthly_rate' => '4.000000',
        ]);
    }

    public function test_instruction_and_financial_result_are_separate_and_cari_link_is_unique(): void
    {
        [$apartment, $account, $payment] = $this->accountWithPayment();
        $due = $this->due($account);
        $instruction = $payment->allocationInstructions()->create(['mode' => 'manual', 'requested_amount' => '30.00']);
        $item = $instruction->items()->create(['due_id' => $due->id, 'position' => 0, 'requested_amount' => '30.00']);
        $allocation = $instruction->allocations()->create([
            'payment_id' => $payment->id,
            'due_id' => $due->id,
            'amount' => '22.50',
            'principal_amount' => '20.00',
            'late_compensation_amount' => '2.50',
        ]);
        $attributes = [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => '2.50',
            'transaction_date' => '2026-11-27',
            'type' => 'debit',
        ];
        $transaction = $allocation->lateCompensationTransaction()->create($attributes);

        $this->assertSame('30.00', $item->fresh()->requested_amount);
        $this->assertSame('20.00', $allocation->fresh()->principal_amount);
        $this->assertTrue($transaction->lateCompensationAllocation->is($allocation));

        $this->expectException(UniqueConstraintViolationException::class);
        $allocation->lateCompensationTransaction()->create($attributes);
    }

    public function test_new_instruction_rejects_a_due_from_another_apartment(): void
    {
        [, , $payment] = $this->accountWithPayment();
        [, $otherAccount] = $this->accountWithPayment();
        $instruction = $payment->allocationInstructions()->create(['mode' => 'manual', 'requested_amount' => '25.00']);

        $this->expectException(DomainException::class);
        $instruction->items()->create([
            'due_id' => $this->due($otherAccount)->id,
            'position' => 0,
            'requested_amount' => '25.00',
        ]);
    }

    public function test_new_cari_link_rejects_a_transaction_from_another_apartment(): void
    {
        [, $account, $payment] = $this->accountWithPayment();
        [$otherApartment, $otherAccount] = $this->accountWithPayment();
        $allocation = $payment->allocations()->create([
            'due_id' => $this->due($account)->id,
            'amount' => '22.50',
            'principal_amount' => '20.00',
            'late_compensation_amount' => '2.50',
        ]);

        $this->expectException(DomainException::class);
        AccountTransaction::create([
            'apartment_id' => $otherApartment->id,
            'account_id' => $otherAccount->id,
            'late_compensation_allocation_id' => $allocation->id,
            'amount' => '2.50',
            'transaction_date' => '2026-11-27',
            'type' => 'debit',
        ]);
    }

    private function accountWithPayment(): array
    {
        $apartment = Apartment::factory()->create();
        $account = $apartment->accounts()->create(['type' => Account::TYPE_OWNER, 'name' => 'Kat maliki']);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => '100.00',
            'unallocated_amount' => '100.00',
            'payment_date' => '2026-11-27',
        ]);

        return [$apartment, $account, $payment];
    }

    private function due(Account $account): Due
    {
        return Due::create([
            'apartment_id' => $account->apartment_id,
            'account_id' => $account->id,
            'due_type' => DueType::Aidat,
            'period' => '2026-11',
            'amount' => '100.00',
            'remaining_amount' => '100.00',
            'status' => 'unpaid',
            'due_date' => '2026-11-15',
        ]);
    }
}
