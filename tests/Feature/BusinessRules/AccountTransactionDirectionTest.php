<?php

namespace Tests\Feature\BusinessRules;

use App\Enums\DueType;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\Category;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finansal yön kurallarını koruyan testler.
 *
 * Bu testler CRUD davranışını değil, bir finansal işlem sonucunda
 * AccountTransaction tablosuna yazılan yönü (debit/credit) doğrular.
 * Amacı, geçmişte birkaç kez bozulan borç/alacak yönünün
 * gelecekteki değişikliklerden korunmasını sağlamaktır.
 */
class AccountTransactionDirectionTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerUser(): User
    {
        return User::factory()->create();
    }

    private function createApartmentFor(User $user): Apartment
    {
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        return $apartment;
    }

    private function createUnitFor(Apartment $apartment): Unit
    {
        return Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
    }

    private function createOwnerAccount(Apartment $apartment, Unit $unit): Account
    {
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);
        $unit->update([
            'owner_account_id' => $account->id,
            'occupant_account_id' => $account->id,
        ]);

        return $account;
    }

    private function createSupplierAccount(Apartment $apartment): Account
    {
        return Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Tedarikçi',
            'is_active' => true,
        ]);
    }

    private function createCashBox(Apartment $apartment): CashBox
    {
        return CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Ana Kasa',
            'is_active' => true,
        ]);
    }

    private function createIncomeCategory(Apartment $apartment): Category
    {
        return Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Aidat',
            'type' => Category::TYPE_INCOME,
            'is_active' => true,
        ]);
    }

    private function createExpenseCategory(Apartment $apartment): Category
    {
        return Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Bakım',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
        ]);
    }

    private function actingAsOwner(User $user, Apartment $apartment): self
    {
        return $this
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user);
    }

    /**
     * Bir aidat borcu oluşturulduğunda ilgili hesap için
     * debit (borç) kaydı oluşmalıdır.
     */
    public function test_creating_due_records_debit_account_transaction(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $unit = $this->createUnitFor($apartment);
        $account = $this->createOwnerAccount($apartment, $unit);
        $category = $this->createIncomeCategory($apartment);

        $this->actingAsOwner($user, $apartment)
            ->post(route('dues.store'), [
                'source_type' => 'manual',
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'period' => '2026-06',
                'due_date' => '2026-06-30',
                'due_type' => DueType::Aidat->value,
                'category_id' => $category->id,
                'source_amount' => 1000,
                'description' => 'Haziran aidatı',
            ])
            ->assertRedirect();

        $due = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($due);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'type' => 'debit',
            'amount' => 1000,
        ]);
    }

    /**
     * Aidat planı üretildiğinde oluşan her borç için
     * ilgili hesaba debit kaydı oluşmalıdır.
     */
    public function test_generating_dues_from_plan_records_debit_transactions(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $unit = $this->createUnitFor($apartment);
        $account = $this->createOwnerAccount($apartment, $unit);
        $category = $this->createIncomeCategory($apartment);

        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'name' => 'Aidat Kararı',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'due_type' => DueType::Aidat,
            'amount_type' => 'monthly',
            'monthly_amount' => 500,
            'distribution_type' => 'equal',
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => 1,
            'is_active' => true,
            'auto_generate' => false,
        ]);

        $this->actingAsOwner($user, $apartment)
            ->post(route('due-plans.generate-month', $plan), [
                'period' => '2026-06',
                'description' => 'Haziran 2026 aidatı',
            ])
            ->assertRedirect();

        $due = Due::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($due);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'type' => 'debit',
            'amount' => 500,
        ]);
    }

    /**
     * Aidat detayından tahsilat alındığında ilgili hesap için
     * credit (alacak/borç azalışı) kaydı oluşmalıdır.
     */
    public function test_receiving_due_payment_records_credit_account_transaction(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $unit = $this->createUnitFor($apartment);
        $account = $this->createOwnerAccount($apartment, $unit);
        $category = $this->createIncomeCategory($apartment);
        $cashBox = $this->createCashBox($apartment);

        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $account->id,
            'due_type' => DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 1000,
            'remaining_amount' => 1000,
            'due_date' => '2026-06-30',
            'status' => 'unpaid',
        ]);

        $this->actingAsOwner($user, $apartment)
            ->post(route('dues.payment.store', $due), [
                'cash_box_id' => $cashBox->id,
                'amount' => 1000,
                'payment_date' => '2026-06-15',
                'description' => 'Haziran aidat tahsilatı',
            ])
            ->assertRedirect();

        $payment = Payment::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'credit',
            'amount' => 1000,
        ]);
    }

    /**
     * Genel ödeme kaydı (malik veya kiracı için) alındığında
     * ilgili hesap için credit kaydı oluşmalıdır.
     */
    public function test_general_payment_to_owner_records_credit_account_transaction(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $unit = $this->createUnitFor($apartment);
        $account = $this->createOwnerAccount($apartment, $unit);
        $cashBox = $this->createCashBox($apartment);

        $this->actingAsOwner($user, $apartment)
            ->post(route('payments.store'), [
                'account_id' => $account->id,
                'amount' => 750,
                'payment_date' => '2026-06-15',
                'cash_box_id' => $cashBox->id,
                'action' => 'save',
                'description' => 'Peşinat ödemesi',
            ])
            ->assertRedirect();

        $payment = Payment::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'credit',
            'amount' => 750,
        ]);
    }

    /**
     * Tedarikçiye ödeme yapıldığında tedarikçi hesabı için
     * debit (alacak azalışı) kaydı oluşmalıdır.
     */
    public function test_supplier_payment_records_debit_account_transaction(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $supplier = $this->createSupplierAccount($apartment);
        $cashBox = $this->createCashBox($apartment);

        $this->actingAsOwner($user, $apartment)
            ->post(route('payments.store'), [
                'account_id' => $supplier->id,
                'amount' => 1200,
                'payment_date' => '2026-06-15',
                'cash_box_id' => $cashBox->id,
                'action' => 'save',
                'description' => 'Tedarikçi ödemesi',
            ])
            ->assertRedirect();

        $payment = Payment::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $supplier->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'debit',
            'amount' => 1200,
        ]);
    }

    /**
     * Gider kaydı oluşturulduğunda tedarikçi hesabı için
     * credit (alacak artışı) kaydı oluşmalıdır.
     */
    public function test_creating_expense_records_credit_account_transaction(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $supplier = $this->createSupplierAccount($apartment);
        $category = $this->createExpenseCategory($apartment);

        $this->actingAsOwner($user, $apartment)
            ->post(route('expenses.store'), [
                'account_id' => $supplier->id,
                'category_id' => $category->id,
                'description' => 'Haziran bakımı',
                'amount' => 800,
                'expense_date' => '2026-06-10',
                'period_month' => '2026-06',
                'is_paid' => false,
            ])
            ->assertRedirect();

        $expense = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $supplier->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'transactionable_type' => Expense::class,
            'transactionable_id' => $expense->id,
            'type' => 'credit',
            'amount' => 800,
        ]);
    }

    /**
     * Gider kaydı ödenmiş olarak oluşturulduğunda tedarikçi için
     * hem credit (gider) hem de debit (ödeme) kaydı oluşmalıdır.
     */
    public function test_creating_paid_expense_records_both_credit_and_debit_transactions(): void
    {
        $user = $this->createOwnerUser();
        $apartment = $this->createApartmentFor($user);
        $supplier = $this->createSupplierAccount($apartment);
        $category = $this->createExpenseCategory($apartment);
        $cashBox = $this->createCashBox($apartment);

        $this->actingAsOwner($user, $apartment)
            ->post(route('expenses.store'), [
                'account_id' => $supplier->id,
                'category_id' => $category->id,
                'description' => 'Haziran bakımı',
                'amount' => 800,
                'expense_date' => '2026-06-10',
                'period_month' => '2026-06',
                'is_paid' => true,
                'payment_date' => '2026-06-15',
                'cash_box_id' => $cashBox->id,
            ])
            ->assertRedirect();

        $expense = Expense::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $supplier->id)
            ->first();

        $payment = Payment::query()
            ->where('apartment_id', $apartment->id)
            ->where('account_id', $supplier->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertNotNull($payment);

        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'transactionable_type' => Expense::class,
            'transactionable_id' => $expense->id,
            'type' => 'credit',
            'amount' => 800,
        ]);

        $this->assertDatabaseHas('account_transactions', [
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'debit',
            'amount' => 800,
        ]);
    }

}
