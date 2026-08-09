<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\AccountTransaction;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_index_opens_empty_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.index'))
            ->assertStatus(200)
            ->assertSee('Henüz gider kaydı yok.');
    }

    public function test_expenses_index_lists_only_selected_apartment_expenses(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        $firstAccount = Account::create([
            'apartment_id' => $firstApartment->id,
            'type' => 'supplier',
            'name' => 'Birinci Tedarikçi',
        ]);
        $secondAccount = Account::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'supplier',
            'name' => 'İkinci Tedarikçi',
        ]);

        Expense::create([
            'apartment_id' => $firstApartment->id,
            'account_id' => $firstAccount->id,
            'category' => 'Temizlik',
            'description' => 'Birinci apartman gideri',
            'amount' => 100,
            'expense_date' => '2026-05-01',
        ]);
        Expense::create([
            'apartment_id' => $secondApartment->id,
            'account_id' => $secondAccount->id,
            'category' => 'Elektrik',
            'description' => 'İkinci apartman gideri',
            'amount' => 200,
            'expense_date' => '2026-05-02',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->get(route('expenses.index'))
            ->assertStatus(200)
            ->assertSee('Elektrik')
            ->assertSee('İkinci Tedarikçi')
            ->assertDontSee('Birinci Tedarikçi');
    }

    public function test_expense_create_form_opens_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => 'supplier',
            'name' => 'Elektrik Firması',
        ]);
        $residentAccount = Account::create([
            'apartment_id' => $apartment->id,
            'type' => 'resident',
            'name' => '1. Daire',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.create'))
            ->assertStatus(200)
            ->assertSee('Gider Ekle')
            ->assertSee($account->name)
            ->assertDontSee($residentAccount->name);
    }

    public function test_user_can_create_expense_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => 'supplier',
            'name' => 'Elektrik Firması',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.store'), [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => 'Mayıs faturası',
                'amount' => 1250.75,
                'expense_date' => '2026-05-03',
                'period_month' => '2026-05',
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'category' => 'Elektrik',
            'period_month' => '2026-05-01 00:00:00',
            'amount' => 1250.75,
            'is_paid' => false,
        ]);
    }

    public function test_user_cannot_create_expense_with_account_from_another_apartment(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);
        $otherAccount = Account::create([
            'apartment_id' => $firstApartment->id,
            'type' => 'supplier',
            'name' => 'Başka Apartman Tedarikçisi',
        ]);
        $category = Category::create([
            'apartment_id' => $secondApartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->post(route('expenses.store'), [
                'account_id' => $otherAccount->id,
                'category_id' => $category->id,
                'amount' => 500,
                'expense_date' => '2026-05-03',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseMissing('expenses', [
            'apartment_id' => $secondApartment->id,
            'account_id' => $otherAccount->id,
        ]);
    }

    public function test_user_cannot_create_expense_with_resident_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $residentAccount = Account::create([
            'apartment_id' => $apartment->id,
            'type' => 'resident',
            'name' => '1. Daire',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.store'), [
                'account_id' => $residentAccount->id,
                'category_id' => $category->id,
                'amount' => 500,
                'expense_date' => '2026-05-03',
            ])
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseMissing('expenses', [
            'apartment_id' => $apartment->id,
            'account_id' => $residentAccount->id,
        ]);
    }

    public function test_expenses_index_shows_action_buttons(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        Expense::create([
            'apartment_id' => $apartment->id,
            'category' => 'Temizlik',
            'amount' => 500,
            'expense_date' => '2026-05-03',
            'is_paid' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.index'))
            ->assertStatus(200)
            ->assertSee('Öde');
    }

    public function test_user_can_update_expense(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Asansör',
            'type' => Category::TYPE_EXPENSE,
        ]);
        $expense = Expense::create([
            'apartment_id' => $apartment->id,
            'category' => 'Temizlik',
            'amount' => 500,
            'expense_date' => '2026-05-03',
            'is_paid' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('expenses.update', $expense), [
                'category_id' => $category->id,
                'amount' => 750,
                'expense_date' => '2026-05-04',
                'period_month' => '2026-04',
                'description' => 'Bakım faturası',
                'is_paid' => '1',
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'category_id' => $category->id,
            'category' => 'Asansör',
            'period_month' => '2026-04-01 00:00:00',
            'amount' => 750,
            'description' => 'Bakım faturası',
            'is_paid' => true,
        ]);
    }

    public function test_user_can_soft_delete_expense(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Asansör',
            'type' => Category::TYPE_EXPENSE,
        ]);
        $expense = Expense::create([
            'apartment_id' => $apartment->id,
            'category' => 'Temizlik',
            'amount' => 500,
            'expense_date' => '2026-05-03',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', [
            'id' => $expense->id,
        ]);
    }

    public function test_user_can_add_expense_payment_to_cash_box(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => 'supplier',
            'name' => 'Elektrik Firması',
        ]);
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Banka Kasası',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);
        $expense = Expense::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'category' => 'Elektrik',
            'amount' => 1250,
            'expense_date' => '2026-05-03',
            'is_paid' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.payment.store', $expense), [
                'cash_box_id' => $cashBox->id,
                'category_id' => $category->id,
                'amount' => 1250,
                'payment_date' => '2026-05-04',
                'description' => 'Elektrik gider ödemesi',
            ])
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('cash_transactions', [
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Elektrik gider ödemesi',
            'amount' => 1250,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'is_paid' => true,
        ]);
    }

    public function test_paid_expense_cannot_be_deleted_without_removing_payment_first(): void
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
            'description' => 'Haziran ödemesi',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 888,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.show', $expense))
            ->assertSessionHas('error', 'Bu giderin ödeme kaydı var. Önce ödemeyi iptal edin.');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_unpaid_expense_without_payment_allocation_can_be_deleted(): void
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
            'paid_amount' => 0,
            'remaining_amount' => 888,
            'expense_date' => '2026-06-22',
            'is_paid' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_expense_detail_page_shows_linked_payment_info(): void
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
            'amount' => 1000,
            'paid_amount' => 500,
            'remaining_amount' => 500,
            'expense_date' => '2026-06-22',
            'is_paid' => false,
        ]);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'amount' => 500,
            'unallocated_amount' => 0,
            'payment_date' => '2026-06-22',
            'description' => 'Kısmi ödeme',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 500,
        ]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'debit',
            'description' => 'Kısmi ödeme',
            'amount' => 500,
            'transaction_date' => '2026-06-22',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.show', $expense))
            ->assertStatus(200)
            ->assertSee('Gideri Kapatan Ödeme')
            ->assertSee('Kısmi ödeme')
            ->assertSee('500,00');
    }

    public function test_partially_paid_expense_cannot_be_deleted(): void
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
            'amount' => 1000,
            'paid_amount' => 400,
            'remaining_amount' => 600,
            'expense_date' => '2026-06-22',
            'is_paid' => false,
        ]);
        $payment = Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'amount' => 400,
            'unallocated_amount' => 0,
            'payment_date' => '2026-06-22',
            'description' => 'Kısmi ödeme',
        ]);
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'expense_id' => $expense->id,
            'amount' => 400,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.show', $expense))
            ->assertSessionHas('error', 'Bu giderin ödeme kaydı var. Önce ödemeyi iptal edin.');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }
}


