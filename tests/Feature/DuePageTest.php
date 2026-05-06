<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dues_index_opens_empty_for_selected_apartment(): void
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
            ->get(route('dues.index'))
            ->assertStatus(200)
            ->assertSee('Henüz aidat kaydı yok.');
    }

    public function test_dues_index_lists_only_selected_apartment_dues(): void
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
            'type' => 'resident',
            'name' => 'Birinci Apartman Hesabı',
        ]);
        $secondAccount = Account::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'resident',
            'name' => 'İkinci Apartman Hesabı',
        ]);

        Due::create([
            'apartment_id' => $firstApartment->id,
            'account_id' => $firstAccount->id,
            'period' => '2026-05',
            'amount' => 100,
            'status' => 'unpaid',
        ]);
        Due::create([
            'apartment_id' => $secondApartment->id,
            'account_id' => $secondAccount->id,
            'period' => '2026-06',
            'amount' => 200,
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->get(route('dues.index'))
            ->assertStatus(200)
            ->assertSee('İkinci Apartman Hesabı')
            ->assertSee('2026-06')
            ->assertDontSee('Birinci Apartman Hesabı');
    }

    public function test_user_can_create_equal_dues_from_expenses_with_multiple_category_filter(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 2,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unitOne = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $unitTwo = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $accountOne = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitOne->id, 'type' => Account::TYPE_OWNER, 'name' => '1. Daire Maliki', 'is_active' => true]);
        $accountTwo = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitTwo->id, 'type' => Account::TYPE_OWNER, 'name' => '2. Daire Maliki', 'is_active' => true]);
        $unitOne->update(['owner_account_id' => $accountOne->id, 'occupant_account_id' => $accountOne->id]);
        $unitTwo->update(['owner_account_id' => $accountTwo->id, 'occupant_account_id' => $accountTwo->id]);
        $dueCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Demirbaş', 'type' => Category::TYPE_INCOME]);
        $fixtureCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Demirbaş Gideri', 'type' => Category::TYPE_EXPENSE]);
        $elevatorCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Asansör', 'type' => Category::TYPE_EXPENSE]);
        $cleaningCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Temizlik', 'type' => Category::TYPE_EXPENSE]);
        Expense::create(['apartment_id' => $apartment->id, 'category_id' => $fixtureCategory->id, 'category' => $fixtureCategory->name, 'amount' => 1000, 'expense_date' => '2026-04-10', 'period_month' => '2026-04-01']);
        Expense::create(['apartment_id' => $apartment->id, 'category_id' => $elevatorCategory->id, 'category' => $elevatorCategory->name, 'amount' => 500, 'expense_date' => '2026-04-12', 'period_month' => '2026-04-01']);
        Expense::create(['apartment_id' => $apartment->id, 'category_id' => $cleaningCategory->id, 'category' => $cleaningCategory->name, 'amount' => 900, 'expense_date' => '2026-04-13', 'period_month' => '2026-04-01']);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_EXPENSES,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'category_id' => $dueCategory->id,
                'source_period' => '2026-04',
                'category_filter_ids' => [$fixtureCategory->id, $elevatorCategory->id],
                'description' => 'Nisan demirbaş ve asansör giderleri',
            ]);

        $this->assertSame(302, $response->status());
        $response->assertSessionHasNoErrors();
        $this->assertSame(route('dues.index'), $response->headers->get('Location'));

        $this->assertDatabaseHas('due_batches', [
            'apartment_id' => $apartment->id,
            'category_id' => $dueCategory->id,
            'source_type' => DueBatch::SOURCE_EXPENSES,
            'source_amount' => 1500,
        ]);
        $this->assertDatabaseHas('dues', ['account_id' => $accountOne->id, 'amount' => 750]);
        $this->assertDatabaseHas('dues', ['account_id' => $accountTwo->id, 'amount' => 750]);
        $this->assertDatabaseHas('account_transactions', ['account_id' => $accountOne->id, 'type' => 'debit', 'amount' => 750]);
        $this->assertDatabaseHas('account_transactions', ['account_id' => $accountTwo->id, 'type' => 'debit', 'amount' => 750]);
    }

    public function test_user_can_create_equal_dues_from_manual_total(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 2]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unitOne = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $unitTwo = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $accountOne = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitOne->id, 'type' => Account::TYPE_OWNER, 'name' => '1. Daire Maliki', 'is_active' => true]);
        $accountTwo = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitTwo->id, 'type' => Account::TYPE_OWNER, 'name' => '2. Daire Maliki', 'is_active' => true]);
        $unitOne->update(['owner_account_id' => $accountOne->id, 'occupant_account_id' => $accountOne->id]);
        $unitTwo->update(['owner_account_id' => $accountTwo->id, 'occupant_account_id' => $accountTwo->id]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_MANUAL,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'category_id' => $category->id,
                'source_amount' => 1200,
                'description' => 'Mayıs aidatı',
            ])
            ->assertRedirect(route('dues.index'));

        $this->assertDatabaseHas('dues', ['account_id' => $accountOne->id, 'amount' => 600]);
        $this->assertDatabaseHas('dues', ['account_id' => $accountTwo->id, 'amount' => 600]);
        $this->assertDatabaseHas('account_transactions', ['account_id' => $accountOne->id, 'type' => 'debit', 'amount' => 600]);
        $this->assertDatabaseHas('account_transactions', ['account_id' => $accountTwo->id, 'type' => 'debit', 'amount' => 600]);
    }

    public function test_user_can_create_individual_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Demirbaş', 'type' => Category::TYPE_INCOME]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_INDIVIDUAL,
                'distribution_type' => DueBatch::DISTRIBUTION_INDIVIDUAL,
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'category_id' => $category->id,
                'account_id' => $account->id,
                'individual_amount' => 450,
                'description' => 'Kumanda bedeli',
            ])
            ->assertRedirect(route('dues.index'));

        $this->assertDatabaseHas('dues', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 450,
            'description' => 'Kumanda bedeli',
        ]);
        $this->assertDatabaseHas('account_transactions', [
            'account_id' => $account->id,
            'type' => 'debit',
            'amount' => 450,
            'description' => 'Kumanda bedeli',
        ]);
    }

    public function test_created_due_debit_is_visible_on_accounts_index(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => Account::TYPE_OWNER, 'name' => '1. Daire Maliki', 'is_active' => true]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Mayıs aidatı',
            'amount' => 600,
            'transaction_date' => '2026-05-31',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.index'))
            ->assertStatus(200)
            ->assertSee('600,00 TL');
    }
}
