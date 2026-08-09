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

class RevertAllocationModalTest extends TestCase
{
    use RefreshDatabase;

    private function setupApartmentAndOwner(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        return [$user, $apartment];
    }

    public function test_due_detail_shows_revert_modal_markup(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'due_id' => $due->id,
            'amount' => 500,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $due))
            ->assertStatus(200)
            ->assertSee('Tahsisatı Geri Al')
            ->assertSee('Sadece Geri Al (Tahsilat Hesapta Kalır)')
            ->assertSee('Tahsilatı da Sil')
            ->assertSee('id="revert-allocation-modal-'.$allocation->id.'"', false);
    }

    public function test_due_detail_unallocate_only_keeps_payment_and_redirects_back(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'due_id' => $due->id,
            'amount' => 500,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('payments.allocations.destroy', [$payment, $allocation]), [
                'redirect_to' => route('dues.show', $due),
            ])
            ->assertRedirect(route('dues.show', $due));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'unallocated_amount' => 500]);
        $this->assertDatabaseHas('dues', ['id' => $due->id, 'remaining_amount' => 500, 'status' => 'unpaid']);
        $this->assertDatabaseMissing('payment_allocations', ['id' => $allocation->id]);
    }

    public function test_due_detail_delete_payment_deletes_allocation_and_redirects_back(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
            ->delete(route('payments.destroy', $payment), [
                'redirect_to' => route('dues.show', $due),
            ])
            ->assertRedirect(route('dues.show', $due));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('dues', ['id' => $due->id, 'remaining_amount' => 500, 'status' => 'unpaid']);
    }

    public function test_due_detail_disables_delete_payment_when_payment_has_multiple_allocations(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı']);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $firstDue = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 300,
            'remaining_amount' => 0,
            'due_date' => '2026-05-31',
            'status' => 'paid',
        ]);
        $secondDue = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 200,
            'remaining_amount' => 0,
            'due_date' => '2026-06-30',
            'status' => 'paid',
        ]);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $tenant->id,
            'amount' => 500,
            'unallocated_amount' => 0,
            'payment_date' => '2026-05-15',
            'description' => 'Çift ay ödemesi',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'due_id' => $firstDue->id,
            'amount' => 300,
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'due_id' => $secondDue->id,
            'amount' => 200,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $firstDue))
            ->assertStatus(200)
            ->assertSee('disabled')
            ->assertSee('Bu tahsilat başka aidatlara/giderlere de tahsis edilmiş; sadece geri alabilirsiniz.');
    }

    public function test_expense_detail_shows_revert_modal_markup(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 888,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.show', $expense))
            ->assertStatus(200)
            ->assertSee('Geri Al')
            ->assertSee('Ödemeyi Sil')
            ->assertSee('id="revert-allocation-modal-'.$allocation->id.'"', false);
    }

    public function test_expense_detail_unallocate_only_keeps_payment_and_redirects_back(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 888,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('payments.allocations.destroy', [$payment, $allocation]), [
                'redirect_to' => route('expenses.show', $expense),
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'unallocated_amount' => 888]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'paid_amount' => 0, 'remaining_amount' => 888, 'is_paid' => false]);
        $this->assertDatabaseMissing('payment_allocations', ['id' => $allocation->id]);
    }

    public function test_expense_detail_delete_payment_deletes_allocation_and_redirects_back(): void
    {
        [$user, $apartment] = $this->setupApartmentAndOwner();
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
            ->delete(route('payments.destroy', $payment), [
                'redirect_to' => route('expenses.show', $expense),
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'paid_amount' => 0, 'remaining_amount' => 888, 'is_paid' => false]);
    }
}
