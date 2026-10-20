<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use App\Models\Category;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\Expense;
use App\Models\ExpensePlan;
use App\Models\Unit;
use App\Models\User;
use App\Support\AssessmentPlanGenerator;
use App\Support\CurrentApartment;
use App\Support\ExpensePlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function setupApartment(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test Apt', 'unit_count' => 2]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);

        $units = [];
        for ($i = 1; $i <= 2; $i++) {
            $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $i, 'square_meters' => 50, 'share_coefficient' => 1]);
            $account = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => $unit->id,
                'type' => Account::TYPE_OWNER,
                'name' => "Malik {$i}",
                'is_active' => true,
            ]);
            $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);
            $units[] = $unit;
        }

        return [$user, $apartment, $units];
    }

    public function test_due_plan_stores_by_year_without_deleting_other_years(): void
    {
        [$user, $apartment] = $this->setupApartment();

        DuePlan::create([
            'apartment_id' => $apartment->id,
            'name' => 'Aidat Kararı',
            'plan_year' => 2025,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'due_type' => 'aidat',
            'amount_type' => 'monthly',
            'monthly_amount' => 800,
            'distribution_type' => 'equal',
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => 1,
            'generation_mode' => 'on_schedule',
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.store'), [
                'plan_year' => 2026,
                'monthly_amount' => 1000,
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'due_day' => 5,
                'generate_day' => 1,
                'generation_mode' => 'on_schedule',
                'is_active' => true,
            ])
            ->assertRedirect(route('due-plans.index', ['year' => 2026]));

        $this->assertSame(2, DuePlan::where('apartment_id', $apartment->id)->count());
        $this->assertDatabaseHas('due_plans', ['apartment_id' => $apartment->id, 'plan_year' => 2025, 'monthly_amount' => 800]);
        $this->assertDatabaseHas('due_plans', ['apartment_id' => $apartment->id, 'plan_year' => 2026, 'monthly_amount' => 1000]);
    }

    public function test_passive_due_plan_blocks_generate_month(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $plan = DuePlan::create([
            'apartment_id' => $apartment->id,
            'name' => 'Aidat Kararı',
            'plan_year' => 2026,
            'due_type' => 'aidat',
            'amount_type' => 'monthly',
            'monthly_amount' => 1000,
            'distribution_type' => 'equal',
            'target_audience' => 'owner_only',
            'due_day' => 1,
            'generate_day' => 1,
            'generation_mode' => 'on_schedule',
            'is_active' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('due-plans.generate-month', $plan), ['period' => '2026-06'])
            ->assertRedirect(route('due-plans.index', ['year' => 2026]));

        $this->assertSame(0, Due::where('apartment_id', $apartment->id)->count());
    }

    public function test_expense_plan_generates_unpaid_expense_once(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $supplier = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Temizlik A.Ş.',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Temizlik',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
        ]);

        $plan = ExpensePlan::create([
            'apartment_id' => $apartment->id,
            'name' => 'Temizlik',
            'category_id' => $category->id,
            'account_id' => $supplier->id,
            'amount' => 2500,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'generate_day' => 1,
            'is_active' => true,
        ]);

        $generator = app(ExpensePlanGenerator::class);
        $this->assertSame(1, $generator->generateForPeriod($plan, '2026-03'));
        $this->assertSame(0, $generator->generateForPeriod($plan, '2026-03'));

        $expense = Expense::where('expense_plan_id', $plan->id)->first();
        $this->assertNotNull($expense);
        $this->assertFalse((bool) $expense->is_paid);
        $this->assertSame('2500.00', (string) $expense->amount);
        $this->assertDatabaseHas('account_transactions', [
            'transactionable_type' => Expense::class,
            'transactionable_id' => $expense->id,
            'type' => 'credit',
        ]);

        $expense->delete();
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertNull($expense->fresh()->plan_occurrence_key);

        $this->assertSame(1, $generator->generateForPeriod($plan, '2026-03'));
        $this->assertSame(2, Expense::withTrashed()->where('expense_plan_id', $plan->id)->count());
        $this->assertSame(1, Expense::where('expense_plan_id', $plan->id)->count());
    }

    public function test_assessment_plan_distribution_and_create_all(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Malik Payı',
            'type' => Category::TYPE_INCOME,
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('assessment-plans.store'), [
                'name' => 'Çatı Yenileme',
                'total_amount' => 1000,
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'category_id' => $category->id,
                'installment_count' => 2,
                'first_due_date' => now()->addMonth()->startOfMonth()->format('Y-m-d'),
                'due_interval_months' => 1,
                'generation_mode' => 'create_all_now',
                'generate_day' => 1,
                'include_past_installments' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $plan = AssessmentPlan::where('apartment_id', $apartment->id)->first();
        $this->assertNotNull($plan);
        $this->assertSame(2, $plan->unitShares()->count());
        $this->assertEquals(1000.0, (float) $plan->unitShares()->sum('share_amount'));
        $this->assertSame(2, $plan->installments()->count());
        $this->assertEquals(1000.0, (float) $plan->installmentLines()->sum('amount'));

        $this->assertTrue(
            $plan->installments()->where('status', AssessmentInstallment::STATUS_GENERATED)->count() >= 1
            || Due::where('apartment_id', $apartment->id)->where('due_type', 'malik_payi')->exists()
        );
    }

    public function test_financial_plans_hub_loads(): void
    {
        [$user, $apartment] = $this->setupApartment();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('financial-plans.index'))
            ->assertOk()
            ->assertSee('Finansal Planlama');
    }
}
