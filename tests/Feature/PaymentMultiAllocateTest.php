<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMultiAllocateTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_allocate_page_renders_open_dues_and_payments(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 500,
            'unallocated_amount' => 500,
            'payment_date' => '2025-08-20',
        ]);

        $due = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'period' => '2025-08',
            'amount' => 1000,
            'remaining_amount' => 1000,
            'due_date' => '2025-08-31',
            'status' => 'unpaid',
            'description' => 'Ağustos aidatı',
        ]);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.payments.multi-allocate', [
                'account' => $account->id,
                'payment_ids' => $payment->id,
            ]));

        $response->assertStatus(200)
            ->assertSee('Ağustos aidatı')
            ->assertSee('name="allocations[0][due_id]"', false)
            ->assertSee('500,00 TL');
    }

    public function test_multi_allocate_store_rejects_total_exceeding_budget(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 500,
            'unallocated_amount' => 500,
            'payment_date' => '2025-08-20',
        ]);

        $due = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'period' => '2025-08',
            'amount' => 1000,
            'remaining_amount' => 1000,
            'due_date' => '2025-08-31',
            'status' => 'unpaid',
        ]);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.payments.multi-allocate.store', $account), [
                'payment_ids' => (string) $payment->id,
                'allocations' => [
                    ['due_id' => $due->id, 'amount' => 1000],
                ],
            ]);

        $response->assertSessionHasErrors('allocations');
    }

    public function test_multi_allocate_store_allows_allocation_within_budget(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 500,
            'unallocated_amount' => 500,
            'payment_date' => '2025-08-20',
        ]);

        $due = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'period' => '2025-08',
            'amount' => 1000,
            'remaining_amount' => 1000,
            'due_date' => '2025-08-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.payments.multi-allocate.store', $account), [
                'payment_ids' => (string) $payment->id,
                'allocations' => [
                    ['due_id' => $due->id, 'amount' => 500],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'due_id' => $due->id,
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'unallocated_amount' => 0,
        ]);

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'remaining_amount' => 500,
            'status' => 'partial',
        ]);
    }
}
