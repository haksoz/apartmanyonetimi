<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
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

    public function test_search_form_preserves_active_filters(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Ahmet Yılmaz', 'is_active' => true]);

        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'period' => '2026-01',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'unpaid',
            'description' => 'Ocak 2026 aidatı',
            'is_imported' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.index', [
                'period' => '2026-01',
                'status' => 'unpaid',
                'source' => 'manual',
                'unit_id' => (string) $unit->id,
                'account_type' => 'owner',
                'show_imported' => '1',
                'sort_by' => 'amount',
                'sort_direction' => 'asc',
            ]))
            ->assertStatus(200)
            ->assertSee('Ocak 2026 aidatı')
            ->assertSee('name="show_imported" value="1"', false)
            ->assertSee('name="unit_id" value="' . $unit->id . '"', false)
            ->assertSee('name="account_type" value="owner"', false)
            ->assertSee('name="sort_by" value="amount"', false)
            ->assertSee('name="sort_direction" value="asc"', false);

        // Word-based search should match descriptions with separators between words
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.index', ['search' => '2026 Ocak', 'show_imported' => '1']))
            ->assertStatus(200)
            ->assertSee('Ocak 2026 aidatı');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.index', ['search' => 'Ahmet Yılmaz', 'show_imported' => '1']))
            ->assertStatus(200)
            ->assertSee('Ocak 2026 aidatı');
    }

    public function test_status_filter_matches_computed_status(): void
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
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);

        $paidDue = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-01',
            'amount' => 500,
            'remaining_amount' => 0,
            'due_date' => '2026-01-31',
            'status' => 'paid',
            'description' => 'Ödenmiş aidat',
        ]);
        $partialDue = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-02',
            'amount' => 500,
            'remaining_amount' => 200,
            'due_date' => '2026-02-28',
            'status' => 'partial',
            'description' => 'Kısmen ödenmiş aidat',
        ]);
        $overdueDue = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2025-01',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => now()->subMonth()->format('Y-m-d'),
            'status' => 'unpaid',
            'description' => 'Gecikmiş aidat',
        ]);
        $pendingDue = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-12',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'unpaid',
            'description' => 'Bekleyen aidat',
        ]);

        $base = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user);

        $base->get(route('dues.index', ['status' => 'paid']))
            ->assertStatus(200)
            ->assertSee('Ödenmiş aidat')
            ->assertDontSee('Kısmen ödenmiş aidat')
            ->assertDontSee('Gecikmiş aidat')
            ->assertDontSee('Bekleyen aidat');

        $base->get(route('dues.index', ['status' => 'partial']))
            ->assertStatus(200)
            ->assertSee('Kısmen ödenmiş aidat')
            ->assertDontSee('Ödenmiş aidat')
            ->assertDontSee('Gecikmiş aidat')
            ->assertDontSee('Bekleyen aidat');

        $base->get(route('dues.index', ['status' => 'overdue']))
            ->assertStatus(200)
            ->assertSee('Gecikmiş aidat')
            ->assertDontSee('Ödenmiş aidat')
            ->assertDontSee('Kısmen ödenmiş aidat')
            ->assertDontSee('Bekleyen aidat');

        $base->get(route('dues.index', ['status' => 'unpaid']))
            ->assertStatus(200)
            ->assertSee('Bekleyen aidat')
            ->assertDontSee('Ödenmiş aidat')
            ->assertDontSee('Kısmen ödenmiş aidat')
            ->assertDontSee('Gecikmiş aidat');

        $base->get(route('dues.index'))
            ->assertStatus(200)
            ->assertSee('Ödenmiş aidat')
            ->assertSee('Kısmen ödenmiş aidat')
            ->assertSee('Gecikmiş aidat')
            ->assertSee('Bekleyen aidat');
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
        $fixtureExpense = Expense::create(['apartment_id' => $apartment->id, 'category_id' => $fixtureCategory->id, 'category' => $fixtureCategory->name, 'amount' => 1000, 'expense_date' => '2026-04-10', 'period_month' => '2026-04-01']);
        $elevatorExpense = Expense::create(['apartment_id' => $apartment->id, 'category_id' => $elevatorCategory->id, 'category' => $elevatorCategory->name, 'amount' => 500, 'expense_date' => '2026-04-12', 'period_month' => '2026-04-01']);
        Expense::create(['apartment_id' => $apartment->id, 'category_id' => $cleaningCategory->id, 'category' => $cleaningCategory->name, 'amount' => 900, 'expense_date' => '2026-04-13', 'period_month' => '2026-04-01']);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_EXPENSES,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'target_audience' => 'tenant_priority',
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'due_type' => \App\Enums\DueType::Aidat->value,
                'category_id' => $dueCategory->id,
                'source_period' => '2026-04',
                'selected_expense_ids' => $fixtureExpense->id . ',' . $elevatorExpense->id,
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
                'target_audience' => 'tenant_priority',
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'due_type' => \App\Enums\DueType::Aidat->value,
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
                'target_audience' => 'tenant_priority',
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'due_type' => \App\Enums\DueType::Aidat->value,
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

    public function test_due_detail_page_shows_transfer_button_for_open_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenantAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $ownerAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenantAccount->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $due))
            ->assertStatus(200)
            ->assertSee('Borç Aktar')
            ->assertSee('Kat Maliki')
            ->assertSee('Kiracı');
    }

    public function test_due_detail_page_hides_transfer_button_for_paid_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenantAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenantAccount->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 0,
            'due_date' => '2026-05-31',
            'status' => 'paid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $due))
            ->assertStatus(200)
            ->assertDontSee('Borç Aktar');
    }

    public function test_user_can_transfer_unpaid_due_to_another_unit_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenantAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $ownerAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenantAccount->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $tenantAccount->id,
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'type' => 'debit',
            'description' => 'Mayıs aidatı',
            'amount' => 500,
            'transaction_date' => '2026-05-31',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.transfer', $due), ['target_account_id' => $ownerAccount->id])
            ->assertRedirect(route('accounts.show', $tenantAccount->id));

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'account_id' => $ownerAccount->id,
            'unit_id' => $unit->id,
        ]);
        $this->assertDatabaseHas('account_transactions', [
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'account_id' => $ownerAccount->id,
            'type' => 'debit',
            'amount' => 500,
        ]);
        $this->assertDatabaseMissing('account_transactions', [
            'transactionable_type' => Due::class,
            'transactionable_id' => $due->id,
            'account_id' => $tenantAccount->id,
        ]);
    }

    public function test_due_transfer_rejects_paid_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $tenantAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $ownerAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenantAccount->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 0,
            'due_date' => '2026-05-31',
            'status' => 'paid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->from(route('dues.show', $due))
            ->post(route('dues.transfer', $due), ['target_account_id' => $ownerAccount->id])
            ->assertRedirect(route('dues.show', $due))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'account_id' => $tenantAccount->id,
        ]);
    }

    public function test_due_transfer_rejects_target_account_from_other_unit(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 2]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unitOne = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $unitTwo = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $tenantAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitOne->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $otherUnitOwner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unitTwo->id, 'type' => Account::TYPE_OWNER, 'name' => '2. Daire Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unitOne->id,
            'account_id' => $tenantAccount->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->from(route('dues.show', $due))
            ->post(route('dues.transfer', $due), ['target_account_id' => $otherUnitOwner->id])
            ->assertRedirect(route('dues.show', $due))
            ->assertSessionHasErrors('target_account_id');

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'account_id' => $tenantAccount->id,
        ]);
    }

    public function test_due_detail_page_shows_transfer_button_for_passive_account_due(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $inactiveTenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Eski Kiracı', 'is_active' => false]);
        Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $due))
            ->assertStatus(200)
            ->assertSee('Borç Aktar')
            ->assertSee('Kat Maliki');
    }

    public function test_user_can_transfer_unpaid_due_from_passive_account_to_active_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $inactiveTenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Eski Kiracı', 'is_active' => false]);
        $ownerAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.transfer', $due), ['target_account_id' => $ownerAccount->id])
            ->assertRedirect(route('accounts.show', $inactiveTenant->id));

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'account_id' => $ownerAccount->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_due_detail_page_shows_transfer_button_when_account_lacks_unit_id(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $inactiveTenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => null, 'type' => Account::TYPE_TENANT, 'name' => 'Eski Kiracı', 'is_active' => false]);
        Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dues.show', $due))
            ->assertStatus(200)
            ->assertSee('Borç Aktar')
            ->assertSee('Kat Maliki');
    }

    public function test_user_can_transfer_imported_due_from_passive_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $inactiveTenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Eski Kiracı', 'is_active' => false]);
        $ownerAccount = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Kat Maliki', 'is_active' => true]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $due = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
            'is_imported' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.transfer', $due), ['target_account_id' => $ownerAccount->id])
            ->assertRedirect(route('accounts.show', $inactiveTenant->id));

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'account_id' => $ownerAccount->id,
            'unit_id' => $unit->id,
            'is_imported' => true,
        ]);
    }

    public function test_batch_due_does_not_appear_as_deleted_plan_in_due_plans_page(): void
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
        DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => null,
            'name' => 'Aidat Kararı',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'tenant_priority',
            'due_day' => 1,
            'generate_day' => 1,
            'is_active' => true,
            'auto_generate' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_MANUAL,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'target_audience' => 'tenant_priority',
                'period' => '2026-05',
                'due_date' => '2026-05-31',
                'due_type' => \App\Enums\DueType::Aidat->value,
                'category_id' => $category->id,
                'source_amount' => 1200,
                'description' => 'Mayıs aidatı',
            ])
            ->assertRedirect(route('dues.index'));

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('due-plans.index'))
            ->assertStatus(200)
            ->assertSee('Aidat Kararı Tanımlama')
            ->assertDontSee('Silinmiş Plan')
            ->assertSee('Mayıs 2026')
            ->assertSee('Oluşturulmadı');
    }

    public function test_plan_generated_month_appears_in_due_plans_page(): void
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
        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => null,
            'name' => 'Aidat Kararı',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'tenant_priority',
            'due_day' => 1,
            'generate_day' => 1,
            'is_active' => true,
            'auto_generate' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.generate-month', $plan), [
                'period' => '2026-06',
                'description' => 'Haziran 2026 aidatı',
            ])
            ->assertRedirect(route('dues.index'));

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('due-plans.index'))
            ->assertStatus(200)
            ->assertSee('Aidat Kararı Tanımlama')
            ->assertSee('Plan Ayları')
            ->assertSee('Haziran 2026')
            ->assertSee('Tamamlandı');
    }

    public function test_incomplete_period_can_be_regenerated_from_plan_page(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 4]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $units = [];
        $accounts = [];
        for ($i = 1; $i <= 4; $i++) {
            $units[$i] = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $i]);
            $accounts[$i] = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $units[$i]->id, 'type' => Account::TYPE_OWNER, 'name' => "{$i}. Daire Maliki", 'is_active' => true]);
            $units[$i]->update(['owner_account_id' => $accounts[$i]->id, 'occupant_account_id' => $accounts[$i]->id]);
        }
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => null,
            'name' => 'Aidat Kararı',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'tenant_priority',
            'due_day' => 1,
            'generate_day' => 1,
            'is_active' => true,
            'auto_generate' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.generate-month', $plan), ['period' => '2026-06'])
            ->assertRedirect(route('dues.index'));

        $batch = DueBatch::query()->where('due_plan_id', $plan->id)->where('period', '2026-06')->first();
        $this->assertNotNull($batch);
        $this->assertIsArray($batch->distribution_snapshot);
        $this->assertCount(4, $batch->distribution_snapshot);

        // Bir dairenin aidatını sil (soft delete)
        $dueToDelete = $batch->dues()->first();
        $dueToDelete->transactions()->delete();
        $dueToDelete->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('due-plans.index'))
            ->assertStatus(200)
            ->assertSee('Haziran 2026')
            ->assertSee('Eksik')
            ->assertSee('3 / 4');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.regenerate-period', $plan), ['period' => '2026-06'])
            ->assertRedirect(route('due-plans.index'));

        $this->assertCount(4, $batch->fresh()->dues);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('due-plans.index'))
            ->assertStatus(200)
            ->assertSee('Haziran 2026')
            ->assertSee('Tamamlandı');
    }

    public function test_plan_uses_system_aidat_category(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki', 'is_active' => true]);
        $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.store'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'monthly_amount' => 1000,
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'due_day' => 1,
                'generate_day' => 1,
                'is_active' => true,
            ])
            ->assertRedirect(route('due-plans.index'));

        $plan = DuePlan::query()->where('apartment_id', $apartment->id)->first();
        $this->assertNotNull($plan);
        $this->assertNotNull($plan->category_id);

        $category = Category::query()->find($plan->category_id);
        $this->assertNotNull($category);
        $this->assertEquals('Aidat', $category->name);
        $this->assertEquals(Category::TYPE_INCOME, $category->type);
        $this->assertTrue($category->is_system);
    }

    public function test_auto_generate_skips_partial_period(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 4]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $units = [];
        $accounts = [];
        for ($i = 1; $i <= 4; $i++) {
            $units[$i] = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $i]);
            $accounts[$i] = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $units[$i]->id, 'type' => Account::TYPE_OWNER, 'name' => "{$i}. Daire Maliki", 'is_active' => true]);
            $units[$i]->update(['owner_account_id' => $accounts[$i]->id, 'occupant_account_id' => $accounts[$i]->id]);
        }

        // Service kullanarak sistem Aidat kategorisini al
        $reconciliation = new \App\Support\AidatPeriodReconciliation();
        $category = $reconciliation->categoryFor($apartment);

        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'name' => 'Aidat Kararı',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => 1,
            'is_active' => true,
            'auto_generate' => true,
        ]);

        // Bir hesaba manuel Aidat borcu oluştur
        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $accounts[1]->id,
            'unit_id' => $units[1]->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        // Komut generate_day kontrolü nedeniyle çalışmaz, reconciliation mantığını test edelim
        $reconciliationResult = $reconciliation->reconcile($plan, '2026-06');
        $this->assertGreaterThan(0, count($reconciliationResult['completed_account_ids']));
        $this->assertGreaterThan(0, $reconciliationResult['target_accounts']->count());

        // createDuesForPeriod ile allowPartial=false çağrısı
        $controller = new \App\Http\Controllers\DuePlanController($reconciliation);
        $count = $controller->createDuesForPeriod($plan, '2026-06', null, false);

        // Yeni borç oluşturulmamalı (kısmi dönem, allowPartial=false)
        $this->assertEquals(0, $count);

        // Sadece 1 borç olmalı (manuel olan)
        $this->assertDatabaseCount('dues', 1);
        $this->assertDatabaseHas('dues', [
            'account_id' => $accounts[1]->id,
            'period' => '2026-06',
        ]);
    }

    public function test_dashboard_popup_shows_for_manager_on_partial_period(): void
    {
        $this->markTestSkipped('SQLite does not support DATE_FORMAT function');

        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 4]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $units = [];
        $accounts = [];
        for ($i = 1; $i <= 4; $i++) {
            $units[$i] = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $i]);
            $accounts[$i] = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $units[$i]->id, 'type' => Account::TYPE_OWNER, 'name' => "{$i}. Daire Maliki", 'is_active' => true]);
            $units[$i]->update(['owner_account_id' => $accounts[$i]->id, 'occupant_account_id' => $accounts[$i]->id]);
        }

        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME, 'is_system' => true]);
        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'name' => 'Aidat Kararı',
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonth()->endOfMonth()->toDateString(),
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => now()->day,
            'is_active' => true,
            'auto_generate' => true,
        ]);

        // Bir hesaba manuel Aidat borcu oluştur
        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $accounts[1]->id,
            'unit_id' => $units[1]->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => now()->format('Y-m'),
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => now()->format('Y-m-d'),
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee('Eksik Aidat Oluşturulsun mu?')
            ->assertSee('1 hesapta Aidat borcu zaten bulunuyor')
            ->assertSee('Kalan 3 hesap');
    }

    public function test_dashboard_popup_creates_only_missing_dues(): void
    {
        $this->markTestSkipped('SQLite does not support DATE_FORMAT function');

        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 4]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $units = [];
        $accounts = [];
        for ($i = 1; $i <= 4; $i++) {
            $units[$i] = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $i]);
            $accounts[$i] = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $units[$i]->id, 'type' => Account::TYPE_OWNER, 'name' => "{$i}. Daire Maliki", 'is_active' => true]);
            $units[$i]->update(['owner_account_id' => $accounts[$i]->id, 'occupant_account_id' => $accounts[$i]->id]);
        }

        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME, 'is_system' => true]);
        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'name' => 'Aidat Kararı',
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->addMonth()->endOfMonth()->toDateString(),
            'due_type' => \App\Enums\DueType::Aidat,
            'amount_type' => DuePlan::AMOUNT_TYPE_MONTHLY,
            'monthly_amount' => 1000,
            'distribution_type' => DuePlan::DISTRIBUTION_EQUAL,
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => now()->day,
            'is_active' => true,
            'auto_generate' => true,
        ]);

        // Bir hesaba manuel Aidat borcu oluştur
        $manualDue = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $accounts[1]->id,
            'unit_id' => $units[1]->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => now()->format('Y-m'),
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => now()->format('Y-m-d'),
            'status' => 'unpaid',
        ]);

        $period = now()->format('Y-m');
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.generate-month', $plan), [
                'period' => $period,
            ])
            ->assertRedirect(route('dues.index'));

        // Toplam 4 borç olmalı (1 manuel + 3 plan)
        $this->assertDatabaseCount('dues', 4);

        // Manuel borç değişmemeli
        $this->assertDatabaseHas('dues', [
            'id' => $manualDue->id,
            'account_id' => $accounts[1]->id,
            'amount' => 250,
        ]);

        // Diğer 3 hesapta plan borcu olmalı
        for ($i = 2; $i <= 4; $i++) {
            $this->assertDatabaseHas('dues', [
                'account_id' => $accounts[$i]->id,
                'period' => $period,
                'due_type' => \App\Enums\DueType::Aidat,
                'category_id' => $category->id,
            ]);
        }
    }

    public function test_aidat_uniqueness_prevents_duplicate_manual_creation(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki', 'is_active' => true]);
        $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);

        // Service kullanarak sistem Aidat kategorisini al
        $reconciliation = new \App\Support\AidatPeriodReconciliation();
        $category = $reconciliation->categoryFor($apartment);

        // İlk Aidat borcu
        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        // Aynı hesap/dönem için ikinci Aidat borcu oluşturmaya çalış (manuel batch)
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_MANUAL,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'target_audience' => 'owner_only',
                'period' => '2026-06',
                'due_date' => '2026-06-01',
                'due_type' => \App\Enums\DueType::Aidat->value,
                'category_id' => $category->id,
                'source_amount' => 500,
            ])
            ->assertRedirect(route('dues.index'));

        // Yine 1 borç olmalı (ikinci oluşturulmamış)
        $this->assertDatabaseCount('dues', 1);
    }

    public function test_aidat_uniqueness_prevents_duplicate_on_update(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 2]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit1 = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $unit2 = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $account1 = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit1->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki 1', 'is_active' => true]);
        $account2 = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit2->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki 2', 'is_active' => true]);
        $unit1->update(['owner_account_id' => $account1->id, 'occupant_account_id' => $account1->id]);
        $unit2->update(['owner_account_id' => $account2->id, 'occupant_account_id' => $account2->id]);

        // Service kullanarak sistem Aidat kategorisini al
        $reconciliation = new \App\Support\AidatPeriodReconciliation();
        $category = $reconciliation->categoryFor($apartment);

        $due1 = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account1->id,
            'unit_id' => $unit1->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account2->id,
            'unit_id' => $unit2->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        // İlk borcu hesap2'ye taşımaya çalış (hesap2'de zaten Aidat borcu var)
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->patch(route('dues.update', $due1), [
                'account_id' => $account2->id,
                'unit_id' => $unit2->id,
                'due_type' => \App\Enums\DueType::Aidat->value,
                'category_id' => $category->id,
                'period' => '2026-06',
                'due_date' => '2026-06-01',
                'amount' => 250,
            ])
            ->assertRedirect();

        // Borç değişmemeli
        $this->assertDatabaseHas('dues', [
            'id' => $due1->id,
            'account_id' => $account1->id,
        ]);
    }

    public function test_due_description_and_created_at_manual_can_be_updated(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki', 'is_active' => true]);
        $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);

        $reconciliation = new \App\Support\AidatPeriodReconciliation();
        $category = $reconciliation->categoryFor($apartment);

        $due = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'created_at_manual' => '2026-06-01',
            'description' => 'Eski açıklama',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->patch(route('dues.update', $due), [
                'account_id' => $account->id,
                'due_type' => \App\Enums\DueType::Aidat->value,
                'category_id' => $category->id,
                'period' => '2026-06',
                'due_date' => '2026-06-01',
                'amount' => 250,
                'created_at_manual' => '2026-06-15',
                'description' => 'Yeni açıklama',
            ])
            ->assertRedirect(route('dues.show', $due));

        $this->assertDatabaseHas('dues', [
            'id' => $due->id,
            'description' => 'Yeni açıklama',
            'created_at_manual' => '2026-06-15 00:00:00',
        ]);
    }

    public function test_aidat_uniqueness_prevents_duplicate_on_transfer(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Akbey Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account1 = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true]);
        $account2 = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => Account::TYPE_OWNER, 'name' => 'Maliki', 'is_active' => true]);
        $unit->update(['owner_account_id' => $account2->id, 'occupant_account_id' => $account1->id]);

        // Service kullanarak sistem Aidat kategorisini al
        $reconciliation = new \App\Support\AidatPeriodReconciliation();
        $category = $reconciliation->categoryFor($apartment);

        $due1 = Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account1->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account2->id,
            'unit_id' => $unit->id,
            'due_type' => \App\Enums\DueType::Aidat,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 250,
            'remaining_amount' => 250,
            'due_date' => '2026-06-01',
            'status' => 'unpaid',
        ]);

        // Kiracıdan malikiye devir yapmaya çalış (malikide zaten Aidat borcu var)
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.transfer', $due1), ['target_account_id' => $account2->id])
            ->assertRedirect();

        // Borç değişmemeli
        $this->assertDatabaseHas('dues', [
            'id' => $due1->id,
            'account_id' => $account1->id,
        ]);
    }
}
