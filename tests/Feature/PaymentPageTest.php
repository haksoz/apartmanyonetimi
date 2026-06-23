<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_payment_allocated_to_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı']);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 0,
            'due_date' => '2026-05-31',
            'status' => 'paid',
        ]);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $tenant->id,
            'amount' => 500,
            'unallocated_amount' => 0,
            'payment_date' => '2026-05-15',
            'description' => 'Mayıs ödemesi',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'due_id' => $due->id,
            'amount' => 500,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('accounts.show', $tenant->id));

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'remaining_amount' => 500,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseMissing('payment_allocations', [
            'payment_id' => $payment->id,
        ]);
        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
        ]);
    }

    public function test_user_can_delete_payment_allocated_to_expense(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $supplier = Account::create(['apartment_id' => $apartment->id, 'type' => Account::TYPE_SUPPLIER, 'name' => 'Tedarikçi']);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Bakım', 'type' => Category::TYPE_EXPENSE]);
        $expense = Expense::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'category_id' => $category->id,
            'category' => 'Bakım',
            'description' => 'Haziran bakımı',
            'amount' => 888,
            'paid_amount' => 888,
            'remaining_amount' => 0,
            'expense_date' => '2026-06-22',
            'is_paid' => true,
        ]);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'amount' => 888,
            'unallocated_amount' => 0,
            'payment_date' => '2026-06-22',
            'description' => 'Haziran 2026 - Bakım ödemesi',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 888,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('payments.destroy', $payment))
            ->assertRedirect(route('accounts.show', $supplier->id));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'paid_amount' => 0,
            'remaining_amount' => 888,
            'is_paid' => false,
        ]);
        $this->assertDatabaseMissing('payment_allocations', [
            'payment_id' => $payment->id,
        ]);
        $this->assertDatabaseMissing('payments', [
            'id' => $payment->id,
        ]);
    }
}
