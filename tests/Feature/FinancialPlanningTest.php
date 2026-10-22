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
            ->saveAidatPlan([
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
        $this->assertSame('2026 Mart - Temizlik', $expense->description);
        $this->assertNull($expense->due_date);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.show', $expense))
            ->assertOk()
            ->assertSee('Kaynak')
            ->assertSee('Gider Planı: Temizlik')
            ->assertSee(route('expense-plans.show', $plan), false);
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

        $this->assertSame(1, $generator->generateForPeriod($plan, '2026-04'));
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $plan->id,
            'description' => '2026 Nisan - Temizlik',
        ]);

        $plan->update(['description' => 'Özel bakım sözleşmesi']);
        $this->assertSame(1, $generator->generateForPeriod($plan, '2026-05'));
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $plan->id,
            'description' => 'Özel bakım sözleşmesi',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->saveExpensePlan(route('expense-plans.store'), [
                'name' => 'Ortak alan temizliği',
                'category_id' => $category->id,
                'account_id' => $supplier->id,
                'amount' => 2500,
                'start_date' => '2026-01-01',
                'generate_day' => 1,
                'description_suffix' => 'ortak alan hizmeti',
                'due_days' => 15,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $suffixPlan = ExpensePlan::where('name', 'Ortak alan temizliği')->firstOrFail();
        $this->assertSame('ortak alan hizmeti', $suffixPlan->description_suffix);
        $this->assertSame(15, $suffixPlan->due_days);
        foreach (['2026-06' => 'Haziran', '2026-07' => 'Temmuz'] as $period => $month) {
            $this->assertSame(1, $generator->generateForPeriod($suffixPlan, $period));
            $this->assertDatabaseHas('expenses', [
                'expense_plan_id' => $suffixPlan->id,
                'description' => "2026 {$month} - Temizlik ortak alan hizmeti",
                'due_date' => $period.'-16 00:00:00',
            ]);
            $this->assertDatabaseHas('account_transactions', [
                'account_id' => $supplier->id,
                'description' => "2026 {$month} - Temizlik ortak alan hizmeti",
            ]);
        }

        $this->get(route('expense-plans.show', $suffixPlan))
            ->assertOk()
            ->assertSee('ortak alan hizmeti')
            ->assertSee('Plan Bilgileri')
            ->assertDontSee('id="expense-plan-form"', false)
            ->assertDontSee('name="amount"', false)
            ->assertSee(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]), false);
        $editResponse = $this->get(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]))->assertOk();
        $this->assertTrue(str_contains($editResponse->getContent(), 'id="expense-plan-form"'), 'Explicit edit link must open form.');
        $editResponse->assertSee('name="amount"', false)->assertSee('İptal');
        $this->from(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]))
            ->saveExpensePlanUpdate($suffixPlan, ['name' => ''])
            ->assertSessionHasErrors('name')
            ->assertRedirect(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]));
        $errorResponse = $this->get(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]))->assertOk();
        $this->assertTrue(str_contains($errorResponse->getContent(), 'id="expense-plan-form"'), 'Validation errors must keep form open.');

        $this->saveExpensePlanUpdate($suffixPlan, [
            'name' => $suffixPlan->name,
            'amount' => 2500,
            'generate_day' => 1,
            'description_suffix' => '',
            'due_days' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $suffixPlan->refresh();
        $this->assertNull($suffixPlan->description_suffix);
        $this->assertSame(0, $suffixPlan->due_days);
        $this->assertSame(1, $generator->generateForPeriod($suffixPlan, '2026-08'));
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $suffixPlan->id,
            'description' => '2026 Ağustos - Temizlik',
            'due_date' => '2026-08-01 00:00:00',
        ]);
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $suffixPlan->id,
            'description' => '2026 Haziran - Temizlik ortak alan hizmeti',
            'due_date' => '2026-06-16 00:00:00',
        ]);

        $suffixPlan->update(['generate_day' => 28, 'due_days' => 15]);
        $this->assertSame(1, $generator->generateForPeriod($suffixPlan, '2026-02'));
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $suffixPlan->id,
            'expense_date' => '2026-02-28 00:00:00',
            'due_date' => '2026-03-15 00:00:00',
        ]);

        $this->saveExpensePlanUpdate($suffixPlan, [
            'name' => $suffixPlan->name,
            'amount' => 2500,
            'generate_day' => 1,
            'due_days' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $suffixPlan->refresh();
        $this->assertNull($suffixPlan->due_days);
        $this->assertSame(1, $generator->generateForPeriod($suffixPlan, '2026-09'));
        $this->assertDatabaseHas('expenses', [
            'expense_plan_id' => $suffixPlan->id,
            'expense_date' => '2026-09-01 00:00:00',
            'due_date' => null,
        ]);

        foreach ([-1, 366, 1.5] as $invalidDays) {
            $this->saveExpensePlanUpdate($suffixPlan, [
                'name' => $suffixPlan->name,
                'amount' => 2500,
                'generate_day' => 1,
                'due_days' => $invalidDays,
            ])->assertSessionHasErrors('due_days');
        }
    }

    public function test_expense_plan_create_shows_category_description_preview(): void
    {
        [$user, $apartment] = $this->setupApartment();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expense-plans.create'))
            ->assertOk()
            ->assertSee('Hesap / Tedarikçi')
            ->assertSee('Oluşacak Gider Açıklaması')
            ->assertSee('id="description-preview"', false)
            ->assertSee('id="description_suffix"', false)
            ->assertSee('id="due_days"', false);
    }

    public function test_assessment_plan_distribution_and_create_all(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Malik Payı',
            'type' => Category::TYPE_ALL,
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->saveAssessmentPlan(route('assessment-plans.store'), [
                'due_type' => 'malik_payi', 'category_mode' => 'existing',
                'name' => 'Çatı Yenileme',
                'total_amount' => 1000,
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'category_id' => $category->id,
                'installment_count' => 2,
                'start_period' => now()->addMonth()->format('Y-m'), 'due_day' => 1,
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

        $this->get(route('assessment-plans.show', $plan))->assertOk()
            ->assertSee('Plan Bilgileri')->assertSee('Düzenle')
            ->assertDontSee('name="name"', false);
        $this->get(route('assessment-plans.show', ['assessmentPlan' => $plan, 'edit' => 1]))
            ->assertOk()->assertSee('name="name"', false)->assertSee('İptal');
        $this->saveAssessmentPlanUpdate($plan, [
            'name' => 'Çatı Bakımı',
            'description' => 'Güncel açıklama',
            'generate_day' => 5,
            'is_active' => 0,
            'total_amount' => 9999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('assessment-plans.show', $plan));
        $this->assertSame('Çatı Bakımı', $plan->fresh()->name);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame('1000.00', $plan->fresh()->total_amount);
        $this->assertEquals(1000.0, (float) $plan->installmentLines()->sum('amount'));
        $this->get(route('assessment-plans.show', $plan))->assertOk()
            ->assertSee('Çatı Bakımı')->assertDontSee('name="name"', false);

        $otherApartment = Apartment::create(['user_id' => $user->id, 'name' => 'Diğer Apartman', 'unit_count' => 1]);
        $otherApartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($otherApartment);
        $this->withSession([CurrentApartment::SESSION_KEY => $otherApartment->id])
            ->saveAssessmentPlanUpdate($plan, [
                'name' => 'Yetkisiz değişiklik', 'generate_day' => 1, 'is_active' => 1,
            ])->assertNotFound();
    }

    private function assessmentPayload(array $overrides = []): array
    {
        return [...[
            'due_type' => 'masraf_yansitma', 'name' => 'Çatı Yenileme', 'total_amount' => 1000.01, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'installment_count' => 3, 'start_period' => '2026-09', 'due_day' => 10,
            'due_interval_months' => 1, 'generation_mode' => 'create_all_now', 'generate_day' => 5, 'is_active' => 1,
        ], ...$overrides];
    }

    public function test_assessment_summary_defers_all_writes_and_preserves_distribution_totals(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('assessment-plans.create'))->assertOk()->assertSee('Borçlanacak Kişi')->assertSee('Kaydet');
        $payload = $this->assessmentPayload();
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk()->assertViewIs('assessment-plans.confirm');
        $this->assertSame(0, AssessmentPlan::count());
        $this->assertSame(0, AssessmentInstallment::count());
        $this->assertDatabaseCount('assessment_unit_shares', 0);
        $this->assertSame(4, $preview->viewData('summary')['count']);
        $updated = $this->post(route('assessment-plans.confirm'), [
            'confirmation_token' => $preview->viewData('token'), 'include_past_installments' => 1, 'update_summary' => 1,
        ])->assertOk();
        $this->assertSame(6, $updated->viewData('summary')['count']);
        $this->assertSame(1000.01, $updated->viewData('summary')['amount']);
        $this->assertSame(0, AssessmentPlan::count());
        $token = $updated->viewData('token');
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $token, 'total_amount' => 1])->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $this->assertSame('1000.01', $plan->total_amount);
        $this->assertSame(6, Due::count());
        $this->assertEquals(1000.01, (float) $plan->installmentLines()->sum('amount'));
        $this->assertEquals(1000.01, (float) $plan->unitShares()->sum('share_amount'));
        $this->assertEquals(1000.01, (float) Due::sum('amount'));
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $token])->assertStatus(419);
        $this->assertSame(1, AssessmentPlan::count());
        $this->get(route('assessment-plans.show', $plan))->assertOk()->assertSee('Borç / Daire:')->assertDontSee('Planı Durdur');
        $this->travelBack();
    }

    public function test_assessment_scheduled_summary_generates_only_approved_past_and_current_installments(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload(['generation_mode' => 'on_schedule']);
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $this->assertTrue($preview->viewData('summary')['current_eligible']);
        $this->assertSame(1, $preview->viewData('summary')['past_count']);
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertSame(0, AssessmentPlan::count());
        $updated = $this->post(route('assessment-plans.confirm'), [
            'confirmation_token' => session('assessment_plan_confirmation.token'),
            'include_past_installments' => 1, 'current_month_action' => 'skip', 'update_summary' => 1,
        ])->assertOk();
        $this->assertSame(2, $updated->viewData('summary')['count']);
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $this->assertSame(['2026-10'], $plan->skipped_periods);
        $this->assertSame(2, Due::count());
        $this->assertSame(0, Due::where('period', '>', '2026-09')->count());
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(2, Due::count());
        app(AssessmentPlanGenerator::class)->generateEligibleInstallments($plan, true);
        $this->assertSame(2, Due::count(), 'Geçmiş seçimi gelecekteki taksitleri oluşturmamalı.');
        $this->travelTo(\Carbon\Carbon::parse('2026-11-05 12:00:00'));
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(4, Due::count());
        $this->assertSame(0, Due::where('period', '2026-10')->count());
        $this->travelBack();
    }

    public function test_assessment_current_create_does_not_include_unselected_past_or_future(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('assessment-plans.store'), $this->assessmentPayload(['generation_mode' => 'on_schedule']))->assertOk();
        $updated = $this->post(route('assessment-plans.confirm'), [
            'confirmation_token' => $preview->viewData('token'), 'current_month_action' => 'create',
        ])->assertOk();
        $this->assertSame(2, $updated->viewData('summary')['count']);
        $this->assertSame(0, Due::count());
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(2, Due::count());
        $this->assertSame(2, Due::where('period', '2026-10')->count());
        $this->assertSame(0, Due::where('period', '<', '2026-10')->count());
        $this->assertSame(0, Due::where('period', '>', '2026-10')->count());
        $this->travelBack();
    }

    public function test_assessment_summary_rechecks_distribution_and_can_return_without_saving(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment, $units] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload(['distribution_type' => 'square_meters']);
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $units[0]->update(['square_meters' => 100]);
        $updated = $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertTrue($updated->viewData('changed'));
        $this->assertSame(0, AssessmentPlan::count());
        $this->assertEquals(666.67, $updated->viewData('summary')['shares'][0]['amount']);
        $this->post(route('assessment-plans.confirm'), [
            'confirmation_token' => $updated->viewData('token'), 'return_to_form' => 1,
        ])->assertRedirect(route('assessment-plans.create'))->assertSessionHasInput('name', $payload['name']);
        $this->assertSame(0, AssessmentPlan::count());
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertStatus(419);
        $this->travelBack();
    }

    public function test_assessment_edit_requires_confirmation_and_rechecks_cancelled_installments(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->saveAssessmentPlan(route('assessment-plans.store'), $this->assessmentPayload(['is_active' => 0]))->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $preview = $this->put(route('assessment-plans.update', $plan), [
            'name' => 'Güncel Plan', 'generate_day' => 5, 'is_active' => 1,
            'total_amount' => 1, 'distribution_type' => 'square_meters', 'installment_count' => 1,
        ])->assertOk();
        $this->assertSame('Çatı Yenileme', $plan->fresh()->name);
        $this->assertFalse($plan->fresh()->is_active);
        $plan->installments()->where('sequence', 3)->update(['status' => AssessmentInstallment::STATUS_CANCELLED]);
        $updated = $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertTrue($updated->viewData('changed'));
        $this->assertSame(2, $updated->viewData('summary')['count']);
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $plan->refresh();
        $this->assertSame('Güncel Plan', $plan->name);
        $this->assertSame('1000.01', $plan->total_amount);
        $this->assertSame('equal', $plan->distribution_type);
        $this->assertSame(3, $plan->installment_count);
        $this->assertSame(2, Due::count());
        $this->assertEquals(1000.01, (float) $plan->installmentLines()->sum('amount'));
        $this->get(route('assessment-plans.show', ['assessmentPlan' => $plan, 'edit' => 1]))->assertOk()->assertSee('Değiştirilemez')->assertSee('Kaydet');
        $this->travelBack();
    }

    public function test_assessment_debt_type_and_category_are_applied_to_every_generated_debt(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Isınma', 'type' => Category::TYPE_ALL, 'is_active' => true]);
        foreach (AssessmentPlan::debtTypes() as $type) {
            $payload = $this->assessmentPayload([
                'name' => $type->label().' Planı', 'due_type' => $type->value,
                'category_mode' => 'existing', 'category_id' => $category->id,
                'start_period' => '2026-11', 'due_day' => 10, 'installment_count' => 2,
            ]);
            $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
            $this->assertSame($type, $preview->viewData('proposed')->due_type);
            $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
            $plan = AssessmentPlan::latest('id')->firstOrFail();
            $this->assertSame($type, $plan->due_type);
            $resolvedCategory = $type->selectsCategory() ? $category->id : Category::where('apartment_id', $apartment->id)->where('name', $type->lockedCategoryName())->firstOrFail()->id;
            $this->assertSame($resolvedCategory, $plan->category_id);
            $dues = Due::whereHas('batch', fn ($query) => $query->where('assessment_plan_id', $plan->id))->get();
            $this->assertCount(4, $dues);
            foreach ($dues as $due) {
                $this->assertSame($type, $due->due_type);
                $this->assertSame($resolvedCategory, $due->category_id);
                $this->assertSame($type, $due->batch->due_type);
                $this->assertSame($resolvedCategory, $due->batch->category_id);
            }
            $this->get(route('assessment-plans.show', $plan))->assertOk()->assertSee($type->label());
        }
        $this->travelBack();
    }

    public function test_assessment_limits_debt_types_and_requires_usable_work_categories(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload();
        foreach (['aidat', 'ceza', 'faiz', 'invalid', ''] as $type) {
            $this->post(route('assessment-plans.store'), [...$payload, 'due_type' => $type])->assertSessionHasErrors('due_type');
        }
        $this->post(route('assessment-plans.store'), [...$payload, 'category_mode' => 'existing'])->assertSessionHasErrors('category_id');
        $this->post(route('assessment-plans.store'), [...$payload, 'category_name' => ' '])->assertSessionHasErrors('category_name');
        foreach (['Gelir', 'Gider', 'Pasif', 'Silinmiş'] as $name) {
            $category = Category::create([
                'apartment_id' => $apartment->id, 'name' => $name,
                'type' => $name === 'Gelir' ? Category::TYPE_INCOME : ($name === 'Gider' ? Category::TYPE_EXPENSE : Category::TYPE_ALL),
                'is_active' => $name !== 'Pasif',
            ]);
            if ($name === 'Silinmiş') $category->delete();
            $this->post(route('assessment-plans.store'), [...$payload, 'category_mode' => 'existing', 'category_id' => $category->id])->assertSessionHasErrors('category_id');
            $this->post(route('assessment-plans.store'), [...$payload, 'category_name' => $name])->assertSessionHasErrors('category_name');
            $this->assertSame($name === 'Gelir' ? Category::TYPE_INCOME : ($name === 'Gider' ? Category::TYPE_EXPENSE : Category::TYPE_ALL), $category->fresh()->type);
        }
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Onarım', 'type' => Category::TYPE_ALL, 'is_active' => true]);
        $preview = $this->post(route('assessment-plans.store'), [...$payload, 'category_mode' => 'existing', 'category_id' => $category->id])->assertOk();
        $category->update(['is_active' => false]);
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertSessionHasErrors('category_id');
        $this->assertSame(0, AssessmentPlan::count());
    }

    public function test_assessment_creates_work_category_only_on_confirmation_and_does_not_rename_it(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload(['category_name' => 'Çatı Onarımı']);
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $this->assertTrue($preview->viewData('summary')['category']['is_new']);
        $this->assertSame('Çatı Onarımı', $preview->viewData('summary')['category']['name']);
        $this->assertFalse(Category::where('apartment_id', $apartment->id)->where('name', 'Çatı Onarımı')->exists());
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $category = $plan->category;
        $this->assertSame(Category::TYPE_ALL, $category->type);
        $this->assertSame('Çatı Onarımı', $category->name);
        $this->assertTrue($category->is_active);
        $this->assertSame(0, Due::where('category_id', '!=', $category->id)->count());
        $this->get(route('expense-plans.create'))->assertOk()->assertViewHas('categories', fn ($categories) => $categories->contains('id', $category->id));
        $this->saveAssessmentPlanUpdate($plan, ['name' => 'Yeni Plan Adı', 'generate_day' => 5, 'is_active' => 1])->assertRedirect();
        $this->assertSame('Çatı Onarımı', $category->fresh()->name);
        $this->assertSame($category->id, $plan->fresh()->category_id);
        $this->travelBack();
    }

    public function test_assessment_reuses_matching_work_category_and_rechecks_it_before_saving(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload();
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => $payload['name'], 'type' => Category::TYPE_ALL, 'is_active' => true]);
        $updated = $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertTrue($updated->viewData('changed'));
        $this->assertFalse($updated->viewData('summary')['category']['is_new']);
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $this->assertSame($category->id, AssessmentPlan::firstOrFail()->category_id);
        $this->assertSame(1, Category::where('apartment_id', $apartment->id)->where('name', $payload['name'])->count());
        $this->saveAssessmentPlan(route('assessment-plans.store'), [...$payload, 'category_name' => 'çatı yenileme'])->assertRedirect();
        $this->assertSame($category->id, AssessmentPlan::latest('id')->firstOrFail()->category_id);
    }

    public function test_assessment_calendar_uses_due_day_start_month_and_installment_interval(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $payload = $this->assessmentPayload([
            'start_period' => '2027-01', 'generate_day' => 2, 'due_day' => 28,
            'installment_count' => 3, 'due_interval_months' => 2,
        ]);
        foreach ([0, 29, 1.5] as $invalidDay) {
            $this->post(route('assessment-plans.store'), [...$payload, 'due_day' => $invalidDay])->assertSessionHasErrors('due_day');
        }
        $this->post(route('assessment-plans.store'), [...$payload, 'start_period' => '2027-13'])->assertSessionHasErrors('start_period');
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $this->assertSame(['2027-01-28', '2027-03-28', '2027-05-28'], array_column($preview->viewData('summary')['rows'], 'due_date'));
        $this->assertSame(['02.01.2027', '02.03.2027', '02.05.2027'], array_column($preview->viewData('summary')['rows'], 'scheduled_date'));
        $this->assertSame(['02.01.2027', '02.03.2027', '02.05.2027'], array_column($preview->viewData('summary')['rows'], 'creation_date'));
        $this->assertSame(0, AssessmentPlan::count());
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $this->assertSame(2, $plan->generate_day);
        $this->assertSame(['2027-01-28', '2027-03-28', '2027-05-28'], $plan->installments->map(fn ($installment) => $installment->due_date->toDateString())->all());
        $this->assertSame(6, Due::count());
        $this->travelBack();
    }

    public function test_assessment_live_preview_groups_equal_shares_and_retains_rounding_ranges(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $response = $this->postJson(route('assessment-plans.distribution-preview'), [
            'total_amount' => 1000.01, 'installment_count' => 3, 'distribution_type' => 'equal',
        ])->assertOk()->assertJsonPath('unit_count', 2)->assertJsonCount(1, 'groups');
        $group = $response->json('groups.0');
        $this->assertSame(2, $group['count']);
        $this->assertEquals(500, $group['share_min']);
        $this->assertEquals(500.01, $group['share_max']);
        $this->assertEquals(166.67, $group['installment_min']);
        $this->assertEquals(166.67, $group['installment_max']);
        $this->assertEquals(166.66, $group['last_min']);
        $this->assertEquals(166.67, $group['last_max']);
        $this->assertSame(0, AssessmentPlan::count());
        $this->assertSame(0, AssessmentInstallment::count());
        $this->assertSame(0, Due::count());
        $this->assertArrayNotHasKey('unit_id', $group);
        $this->assertArrayNotHasKey('accounts', $group);
    }

    public function test_assessment_live_preview_groups_by_weight_and_excludes_units_without_weights(): void
    {
        [$user, $apartment, $units] = $this->setupApartment();
        $units[0]->update(['square_meters' => 80, 'share_coefficient' => 1]);
        $units[1]->update(['square_meters' => 120, 'share_coefficient' => 2]);
        Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '3', 'square_meters' => 80, 'share_coefficient' => 1]);
        Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '4', 'square_meters' => 0, 'share_coefficient' => 0]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->postJson(route('assessment-plans.distribution-preview'), [
            'total_amount' => 28000, 'installment_count' => 4, 'distribution_type' => 'square_meters',
            'apartment_id' => 999999,
        ])->assertOk()->assertJsonPath('unit_count', 3)->assertJsonPath('excluded_count', 1)
            ->assertJsonCount(2, 'groups')->assertJsonPath('groups.0.count', 2)
            ->assertJsonPath('groups.1.count', 1)->assertJsonFragment(['share_min' => 8000, 'installment_min' => 2000])
            ->assertJsonFragment(['share_min' => 12000, 'installment_min' => 3000]);
        $this->postJson(route('assessment-plans.distribution-preview'), [
            'total_amount' => 16000, 'installment_count' => 4, 'distribution_type' => 'share_coefficient',
        ])->assertOk()->assertJsonCount(2, 'groups')->assertJsonPath('excluded_count', 1)
            ->assertJsonFragment(['share_min' => 4000, 'installment_min' => 1000])
            ->assertJsonFragment(['share_min' => 8000, 'installment_min' => 2000]);
        $this->postJson(route('assessment-plans.distribution-preview'), [
            'total_amount' => 0, 'installment_count' => 61, 'distribution_type' => 'invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['total_amount', 'installment_count', 'distribution_type']);
        $this->assertSame(0, AssessmentPlan::count());
    }

    public function test_assessment_descriptions_are_automatic_and_preserved_for_existing_debts(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('assessment-plans.create'))->assertOk()->assertDontSee('name="description"', false)->assertSee('Oluşacak Borç Açıklaması');
        $payload = $this->assessmentPayload([
            'name' => 'Çatı Yenileme', 'installment_count' => 4, 'start_period' => '2027-01',
            'description' => 'Elle yazılmış açıklama',
        ]);
        $preview = $this->post(route('assessment-plans.store'), $payload)->assertOk();
        $this->assertSame(['Çatı Yenileme 1/4', 'Çatı Yenileme 2/4', 'Çatı Yenileme 3/4', 'Çatı Yenileme 4/4'], array_column($preview->viewData('summary')['rows'], 'description'));
        $this->post(route('assessment-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $plan = AssessmentPlan::firstOrFail();
        $this->assertNull($plan->description);
        $this->assertSame(8, Due::count());
        foreach ($plan->installments as $installment) {
            $description = 'Çatı Yenileme '.$installment->sequence.'/4';
            $this->assertSame($description, $installment->batch->description);
            foreach ($installment->lines as $line) {
                $this->assertSame($description, $line->due->description);
                $this->assertDatabaseHas('account_transactions', ['transactionable_type' => Due::class, 'transactionable_id' => $line->due_id, 'description' => $description]);
            }
        }
        $this->saveAssessmentPlanUpdate($plan, ['name' => 'Yeni Plan Adı', 'generate_day' => 5, 'is_active' => 1, 'description' => 'Değiştir'])->assertRedirect();
        $this->assertNull($plan->fresh()->description);
        $this->assertSame(2, Due::where('description', 'Çatı Yenileme 1/4')->count());
        $this->travelBack();
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

    public function test_aidat_plan_only_creates_approved_past_and_current_periods(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'due_day' => 5, 'generate_day' => 1, 'is_active' => 1, 'generation_mode' => 'create_all_now',
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('due-plans.index', ['year' => 2026]))->assertOk()
            ->assertDontSee('Geçmiş aylara ait aidatları da oluştur')
            ->assertDontSee('Tümünü şimdi oluştur')->assertDontSee('name="generation_mode"', false);
        $this->saveAidatPlan([...$payload, 'is_active' => 0, 'include_past_periods' => 1])->assertRedirect();
        $this->assertSame(0, Due::count());
        $this->saveAidatPlan($payload)->assertRedirect();
        $this->assertSame(0, Due::count());
        $this->saveAidatPlan([...$payload, 'include_past_periods' => 1])->assertRedirect();
        $this->assertSame(18, Due::count());
        $this->saveAidatPlan([...$payload, 'include_past_periods' => 1])->assertRedirect();
        $this->assertSame(18, Due::count());
        $this->saveAidatPlan([...$payload, 'current_month_action' => 'create'])->assertRedirect();
        $this->assertSame(20, Due::count());
        $plan = DuePlan::firstOrFail();
        $this->assertSame('on_schedule', $plan->generation_mode);
        $this->assertTrue($plan->auto_generate);
        $this->assertSame(0, app(\App\Http\Controllers\DuePlanController::class)->createDuesForPeriod($plan, '2026-11'));
        $this->assertSame(0, Due::where('period', '>', '2026-10')->count());
        $this->travelBack();
    }

    public function test_aidat_plan_only_deduplicates_its_own_source(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment, $units] = $this->setupApartment();
        $reconciliation = app(\App\Support\AidatPeriodReconciliation::class);
        $category = $reconciliation->categoryFor($apartment);
        $planData = [
            'apartment_id' => $apartment->id, 'name' => 'Aidat Kararı',
            'plan_year' => 2026, 'category_id' => $category->id, 'due_type' => 'aidat',
            'amount_type' => 'monthly', 'monthly_amount' => 1000,
            'distribution_type' => 'equal', 'target_audience' => 'owner_only',
            'due_day' => 5, 'generate_day' => 1, 'generation_mode' => 'on_schedule', 'is_active' => true,
        ];
        $plan = DuePlan::create($planData);
        $batchData = [
            'apartment_id' => $apartment->id, 'due_type' => 'aidat', 'category_id' => $category->id,
            'source_type' => 'manual', 'distribution_type' => 'equal', 'target_audience' => 'owner_only',
            'period' => '2026-10', 'source_amount' => 1000,
        ];
        $manualBatch = \App\Models\DueBatch::create($batchData);
        $dueData = [
            'apartment_id' => $apartment->id, 'unit_id' => $units[0]->id,
            'account_id' => $units[0]->owner_account_id, 'category_id' => $category->id,
            'due_type' => 'aidat', 'period' => '2026-10', 'amount' => 123,
            'remaining_amount' => 123, 'due_date' => '2026-10-05', 'status' => 'unpaid',
        ];
        $manual = Due::create($dueData);
        Due::create([...$dueData, 'due_batch_id' => $manualBatch->id]);
        $otherPlan = DuePlan::create([...$planData, 'plan_year' => 2025]);
        $otherBatch = \App\Models\DueBatch::create([...$batchData, 'due_plan_id' => $otherPlan->id]);
        Due::create([...$dueData, 'due_batch_id' => $otherBatch->id]);

        $this->assertCount(0, $reconciliation->reconcile($plan, '2026-10')['completed_account_ids']);
        $this->assertCount(2, $reconciliation->reconcile($plan, '2026-10')['missing_accounts']);
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $planDues = Due::whereHas('batch', fn ($query) => $query->where('due_plan_id', $plan->id))->get();
        $this->assertCount(2, $planDues);
        $this->assertSame(['500.00', '500.00'], $planDues->pluck('amount')->all());
        $this->assertSame('123.00', $manual->fresh()->amount);
        $this->assertCount(2, $reconciliation->reconcile($plan, '2026-10')['completed_account_ids']);
        $controller = app(\App\Http\Controllers\DuePlanController::class);
        $this->assertSame(0, $controller->createDuesForPeriod($plan, '2026-10'));
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(5, Due::where('apartment_id', $apartment->id)->count());
        $query = ['due_type' => 'aidat', 'source' => 'plan', 'plan' => 'aidat:'.$plan->id, 'period' => '2026-10'];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->get(route('due-plans.index', ['year' => 2026]))->assertOk()
            ->assertSee(route('dues.index', $query));
        $filtered = $this->get(route('dues.index', $query))->assertOk()
            ->assertSee('Aidat Kararı (2026)')
            ->assertSee('Aidat Planı')
            ->assertSee('Aidat')
            ->assertDontSee('Borçlandırma Grubu #')
            ->assertSee(route('dues.index', ['due_type' => 'aidat', 'source' => 'plan', 'period' => '2026-10']))
            ->assertSee('Tümünü temizle');
        $this->assertSame(2, $filtered->viewData('dues')->total());
        $this->assertEqualsCanonicalizing($planDues->pluck('id')->all(), $filtered->viewData('dues')->pluck('id')->all());
        $this->get(route('dues.index', ['period' => '2026-10', 'source' => 'plan']))->assertOk()
            ->assertDontSee('Borçlandırma Grubu #');
        $this->assertSame(5, $this->get(route('dues.index'))->viewData('dues')->total());
        $this->travelBack();
    }

    public function test_aidat_save_requires_preview_and_confirmation_for_new_and_existing_plans(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'due_day' => 5, 'generate_day' => 1,
            'current_month_action' => 'create', 'is_active' => 1,
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('due-plans.store'), $payload)->assertOk()
            ->assertViewIs('due-plans.confirm')->assertSee('Henüz kayıt yapılmadı.');
        $this->assertSame(2, $preview->viewData('summary')['count']);
        $this->assertEquals(1000, $preview->viewData('summary')['amount']);
        $this->assertSame(0, DuePlan::count());
        $this->assertSame(0, Due::count());
        $this->assertSame(0, Category::count());
        $token = $preview->viewData('token');
        $this->post(route('due-plans.store'), ['confirmation_token' => $token, 'monthly_amount' => 99999])
            ->assertRedirect(route('due-plans.index', ['year' => 2026]));
        $plan = DuePlan::firstOrFail();
        $this->assertSame('1000.00', $plan->monthly_amount);
        $this->assertSame(2, Due::count());
        $this->post(route('due-plans.store'), ['confirmation_token' => $token])->assertSessionHas('error');
        $this->assertSame(2, Due::count());

        $preview = $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 1])->assertOk();
        $this->assertSame(18, $preview->viewData('summary')['count']);
        $this->assertEquals(9000, $preview->viewData('summary')['amount']);
        $this->assertSame(2, Due::count());
        $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token'), 'return_to_form' => 1])
            ->assertRedirect(route('due-plans.index', ['year' => 2026, 'edit' => 1]));
        $this->assertSame(2, Due::count());

        $preview = $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 1])->assertOk();
        // The saved plan changes while the summary is being reviewed.
        $plan->update(['monthly_amount' => 1200]);
        $refreshed = $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')])
            ->assertOk()->assertViewHas('changed', true);
        $this->assertSame(2, Due::count());
        $this->assertSame('1200.00', $plan->fresh()->monthly_amount);
        $this->post(route('due-plans.store'), ['confirmation_token' => $refreshed->viewData('token')])->assertRedirect();
        $this->assertSame(20, Due::count());
        $preview = $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 1])->assertOk();
        $this->assertSame(0, $preview->viewData('summary')['count']);
        $this->assertEquals(0, $preview->viewData('summary')['amount']);
        $this->travelBack();
    }

    public function test_partial_aidat_confirmation_uses_full_plan_shares(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'due_day' => 5, 'generate_day' => 1,
            'current_month_action' => 'create', 'is_active' => 1,
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->saveAidatPlan($payload)->assertRedirect();
        Due::orderBy('id')->firstOrFail()->delete();
        $preview = $this->post(route('due-plans.store'), $payload)->assertOk();
        $this->assertSame(1, $preview->viewData('summary')['count']);
        $this->assertEquals(500, $preview->viewData('summary')['amount']);
        $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $this->assertSame(2, Due::count());
        $this->assertEquals(1000, Due::sum('amount'));
        $this->travelBack();
    }

    public function test_scheduled_plan_requires_current_month_choice_and_persists_skip(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'due_day' => 5, 'generate_day' => 1,
            'generation_mode' => 'on_schedule', 'is_active' => 1,
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('due-plans.store'), $payload)->assertOk()
            ->assertSee('Bu ayı atla, gelecek aydan başlat.');
        $this->assertTrue($preview->viewData('summary')['current']['eligible']);
        $this->assertSame(2, $preview->viewData('summary')['current']['count']);
        $this->assertSame(0, DuePlan::count());
        $noChoice = $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertSame(0, DuePlan::count());
        $skip = $this->post(route('due-plans.store'), [
            'confirmation_token' => $noChoice->viewData('token'), 'current_month_action' => 'skip',
        ])->assertOk();
        $this->assertSame(0, $skip->viewData('summary')['count']);
        $this->post(route('due-plans.store'), ['confirmation_token' => $skip->viewData('token')])->assertRedirect();
        $plan = DuePlan::firstOrFail();
        $this->assertTrue($plan->skipsPeriod('2026-10'));
        $this->artisan('dues:auto-generate')->assertExitCode(0);
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(0, Due::count());
        $this->get(route('due-plans.index', ['year' => 2026]))->assertOk()->assertSee('Atlandı');

        $preview = $this->post(route('due-plans.store'), $payload)->assertOk();
        $create = $this->post(route('due-plans.store'), [
            'confirmation_token' => $preview->viewData('token'), 'current_month_action' => 'create',
        ])->assertOk();
        $this->assertSame(2, $create->viewData('summary')['count']);
        $this->assertEquals(1000, $create->viewData('summary')['amount']);
        $this->assertSame(0, Due::count());
        $this->post(route('due-plans.store'), ['confirmation_token' => $create->viewData('token')])->assertRedirect();
        $this->assertFalse($plan->fresh()->skipsPeriod('2026-10'));
        $this->assertSame(2, Due::count());
        $complete = $this->post(route('due-plans.store'), $payload)->assertOk();
        $this->assertFalse($complete->viewData('summary')['current']['eligible']);

        Due::firstOrFail()->delete();
        $partial = $this->post(route('due-plans.store'), [...$payload, 'current_month_action' => 'create'])->assertOk();
        $this->assertSame(1, $partial->viewData('summary')['count']);
        $this->assertEquals(500, $partial->viewData('summary')['amount']);
        $this->post(route('due-plans.store'), ['confirmation_token' => $partial->viewData('token')])->assertRedirect();
        $this->assertSame(2, Due::count());
        $this->assertEquals(1000, Due::sum('amount'));

        $plan->refresh()->update(['skipped_periods' => ['2026-10']]);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 12:00:00'));
        $this->artisan('dues:auto-generate')->assertExitCode(0);
        $this->assertSame(2, Due::where('period', '2026-11')->count());
        $this->assertTrue($plan->fresh()->skipsPeriod('2026-10'));
        $this->travelBack();
    }

    public function test_current_month_question_only_appears_when_needed(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'due_day' => 5, 'generate_day' => 28,
            'generation_mode' => 'on_schedule', 'is_active' => 1,
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach ([$payload, [...$payload, 'generate_day' => 1, 'is_active' => 0], [...$payload, 'plan_year' => 2027, 'generate_day' => 1], [...$payload, 'generation_mode' => 'create_all_now']] as $input) {
            $preview = $this->post(route('due-plans.store'), $input)->assertOk();
            $this->assertFalse($preview->viewData('summary')['current']['eligible']);
        }
        $dueToday = $this->post(route('due-plans.store'), [...$payload, 'generate_day' => 21])->assertOk();
        $this->assertTrue($dueToday->viewData('summary')['current']['eligible']);
        $this->travelBack();
    }

    public function test_aidat_plan_always_bills_the_tenant_with_owner_fallback(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment, $units] = $this->setupApartment();
        $tenant = Account::create([
            'apartment_id' => $apartment->id, 'unit_id' => $units[0]->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Kiracı', 'is_active' => true,
        ]);
        \App\Models\TenantAssignment::create([
            'apartment_id' => $apartment->id, 'unit_id' => $units[0]->id,
            'account_id' => $tenant->id, 'move_in_date' => '2026-10-01',
        ]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('due-plans.index', ['year' => 2026]))->assertOk()
            ->assertSee('İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.')
            ->assertDontSee('name="target_audience"', false)->assertDontSee('Sadece Sahipler');
        $preview = $this->post(route('due-plans.store'), [
            'plan_year' => 2026, 'monthly_amount' => 1000, 'distribution_type' => 'equal',
            'target_audience' => 'owner_only', 'due_day' => 5, 'generate_day' => 1,
            'current_month_action' => 'create', 'generation_mode' => 'create_all_now', 'is_active' => 1,
        ])->assertOk();
        $this->assertSame('tenant_priority', $preview->viewData('proposed')->target_audience);
        $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $plan = DuePlan::firstOrFail();
        $this->assertSame('tenant_priority', $plan->target_audience);
        $this->assertSame(1, Due::where('account_id', $tenant->id)->count());
        $this->assertSame(0, Due::where('account_id', $units[0]->owner_account_id)->count());
        $this->assertSame(1, Due::where('account_id', $units[1]->owner_account_id)->count());
        $plan->update(['target_audience' => 'owner_only']);
        $this->assertSame('tenant_priority', $plan->fresh()->target_audience);
        \App\Models\TenantAssignment::where('account_id', $tenant->id)->update(['move_out_date' => '2026-10-31']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 12:00:00'));
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(1, Due::where('account_id', $tenant->id)->count());
        $this->assertDatabaseHas('dues', ['account_id' => $units[0]->owner_account_id, 'period' => '2026-11']);
        $this->travelBack();
    }

    public function test_a_new_aidat_plan_can_replace_an_inactive_plan_for_the_same_year(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $data = [
            'apartment_id' => $apartment->id, 'name' => 'Önceki Aidat Planı', 'plan_year' => 2026,
            'due_type' => 'aidat', 'amount_type' => 'monthly', 'monthly_amount' => 1000,
            'distribution_type' => 'equal', 'due_day' => 5, 'generate_day' => 28, 'is_active' => false,
        ];
        $old = DuePlan::create($data);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('financial-plans.create'))->assertOk()->assertViewHas('hasCurrentYearAidatPlan', false);
        $this->post(route('financial-plans.select-type'), ['type' => 'aidat'])
            ->assertRedirect(route('due-plans.index', ['year' => 2026, 'new' => 1]));
        $this->get(route('due-plans.index', ['year' => 2026, 'new' => 1]))->assertOk()
            ->assertViewHas('plan', fn ($plan) => ! $plan->exists)
            ->assertSee('name="creating_new"', false);
        $payload = [
            'creating_new' => 1, 'plan_year' => 2026, 'monthly_amount' => 2000,
            'distribution_type' => 'equal', 'due_day' => 5, 'generate_day' => 28, 'is_active' => 1,
        ];
        $preview = $this->post(route('due-plans.store'), $payload)->assertOk();
        $this->assertSame(1, DuePlan::count());
        $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $active = DuePlan::where('is_active', true)->firstOrFail();
        $this->assertNotSame($old->id, $active->id);
        $this->assertSame('1000.00', $old->fresh()->monthly_amount);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('2000.00', $active->monthly_amount);
        $this->assertSame(2, DuePlan::where('plan_year', 2026)->count());
        $this->get(route('financial-plans.index'))->assertOk()
            ->assertSee(route('due-plans.index', ['year' => 2026, 'plan_id' => $old->id]))
            ->assertSee(route('due-plans.index', ['year' => 2026, 'plan_id' => $active->id]));
        $this->get(route('due-plans.index', ['year' => 2026, 'plan_id' => $old->id]))
            ->assertOk()->assertViewHas('plan', fn ($plan) => $plan->id === $old->id);
        $this->get(route('due-plans.index', ['year' => 2026]))
            ->assertOk()->assertViewHas('plan', fn ($plan) => $plan->id === $active->id);
        $this->get(route('financial-plans.create'))->assertViewHas('hasCurrentYearAidatPlan', true);
        $this->post(route('due-plans.store'), $payload)->assertSessionHasErrors('plan_year');
        $this->post(route('due-plans.store'), [...$payload, 'creating_new' => 0, 'plan_id' => $old->id])
            ->assertSessionHasErrors('is_active');
        $this->patch(route('due-plans.activate', $old))->assertSessionHasErrors('is_active');
        $this->assertSame(1, DuePlan::where('plan_year', 2026)->where('is_active', true)->count());
        $this->travelBack();
    }

    public function test_past_period_choice_is_made_in_the_confirmation_summary(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('due-plans.index', ['year' => 2026, 'new' => 1]))->assertOk()
            ->assertSee('Kaydet')->assertDontSee('Özeti Gör')->assertDontSee('id="include_past_periods"', false);
        $preview = $this->post(route('due-plans.store'), [
            'creating_new' => 1, 'plan_year' => 2026, 'monthly_amount' => 1000,
            'distribution_type' => 'equal', 'generate_day' => 1, 'due_day' => 5, 'is_active' => 1,
        ])->assertOk()->assertSee('Geçmiş Aylar')->assertSee('Geçmiş aylara ait aidatları da oluştur');
        $this->assertSame(18, $preview->viewData('summary')['past']['count']);
        $this->assertFalse($preview->viewData('summary')['past']['included']);
        $updated = $this->post(route('due-plans.store'), [
            'confirmation_token' => $preview->viewData('token'), 'include_past_periods' => 1,
            'current_month_action' => 'skip', 'update_summary' => 1,
        ])->assertOk()->assertViewHas('changed', false);
        $this->assertSame(18, $updated->viewData('summary')['count']);
        $this->assertEquals(9000, $updated->viewData('summary')['amount']);
        $this->assertSame(0, DuePlan::count());
        $this->assertSame(0, Due::count());
        $unchecked = $this->post(route('due-plans.store'), [
            'confirmation_token' => $updated->viewData('token'), 'include_past_periods' => 0,
            'current_month_action' => 'skip', 'update_summary' => 1,
        ])->assertOk();
        $this->assertSame(0, $unchecked->viewData('summary')['count']);
        $unchanged = $this->post(route('due-plans.store'), [
            'confirmation_token' => $unchecked->viewData('token'), 'include_past_periods' => 0,
            'current_month_action' => 'skip', 'update_summary' => 1,
        ])->assertOk();
        $this->assertSame(0, DuePlan::count());
        $final = $this->post(route('due-plans.store'), [
            'confirmation_token' => $unchanged->viewData('token'), 'include_past_periods' => 1,
            'current_month_action' => 'skip',
        ])->assertOk();
        $this->post(route('due-plans.store'), [
            'confirmation_token' => $final->viewData('token'), 'include_past_periods' => 1,
            'current_month_action' => 'skip',
        ])->assertRedirect();
        $this->assertSame(18, Due::count());
        $this->assertSame(0, Due::where('period', '>=', '2026-10')->count());
        $this->travelBack();
    }

    private function expensePlanPayload(Apartment $apartment): array
    {
        $supplier = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Elektrik Şirketi',
            'is_active' => true,
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
        ]);

        return [
            'name' => 'Elektrik Planı',
            'account_id' => $supplier->id,
            'category_id' => $category->id,
            'amount' => 1250,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'generate_day' => 5,
            'due_days' => 15,
            'description_suffix' => 'ortak alan tüketimi',
            'generation_mode' => ExpensePlan::GENERATION_CREATE_ALL_NOW,
            'is_active' => 1,
        ];
    }

    public function test_aidat_plans_are_opened_from_the_hub_with_expired_status(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-31 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        foreach ([2024 => false, 2025 => true, 2026 => true] as $year => $active) {
            DuePlan::create([
                'apartment_id' => $apartment->id,
                'name' => 'Aidat Kararı',
                'plan_year' => $year,
                'due_type' => 'aidat',
                'amount_type' => 'monthly',
                'monthly_amount' => 1000,
                'distribution_type' => 'equal',
                'target_audience' => 'owner_only',
                'due_day' => 5,
                'generate_day' => 1,
                'is_active' => $active,
            ]);
        }

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)->get(route('financial-plans.index'))
            ->assertOk()->assertSee('Süresi Doldu')->assertSee('Pasif')->assertSee('Aktif');
        $rows = $response->viewData('plans')->keyBy('period_label');
        $this->assertTrue($rows['2025']['is_expired']);
        $this->assertFalse($rows['2024']['is_expired']);
        $this->assertFalse($rows['2026']['is_expired']);
        foreach ([2024, 2025, 2026] as $year) {
            $response->assertSee(route('due-plans.index', ['year' => $year]), false);
        }

        $this->get(route('due-plans.index', ['year' => 2025]))
            ->assertOk()->assertSee('Aidat Planı · 2025')
            ->assertSee(route('financial-plans.index'), false)
            ->assertDontSee('id="year"', false)
            ->assertDontSee('history.back()', false);
        $this->get(route('due-plans.index', ['year' => 2026]))->assertOk()
            ->assertSee('Plan Bilgileri')->assertDontSee('id="monthly_amount"', false);
        $this->get(route('due-plans.index', ['year' => 2026, 'edit' => 1]))->assertOk()
            ->assertSee('id="monthly_amount"', false)->assertSee('İptal');
        $this->assertTrue(DuePlan::where('plan_year', 2025)->firstOrFail()->is_active);

        $this->get(route('financial-plans.create'))
            ->assertOk()
            ->assertViewHas('hasCurrentYearAidatPlan', true)
            ->assertSee('2026 yılı için aktif aidat planı var.')
            ->assertSee('cursor-not-allowed bg-slate-50 opacity-50', false)
            ->assertSee('value="aidat" class="mt-1" required disabled', false)
            ->assertDontSee('Mevcut aidat planını düzenle');
        $this->post(route('financial-plans.select-type'), ['type' => 'aidat'])
            ->assertRedirect(route('financial-plans.create'))
            ->assertSessionHasErrors('type');
        $this->post(route('financial-plans.select-type'), ['type' => 'expense'])
            ->assertRedirect(route('expense-plans.create'));

        DuePlan::where('plan_year', 2026)->update(['is_active' => false]);
        $this->get(route('financial-plans.create'))->assertViewHas('hasCurrentYearAidatPlan', false);
        DuePlan::where('plan_year', 2026)->update(['is_active' => true]);

        $this->travelTo(\Carbon\Carbon::parse('2027-01-01 00:00:00'));
        $rows = $this->get(route('financial-plans.index'))->assertOk()->viewData('plans')->keyBy('period_label');
        $this->assertTrue($rows['2026']['is_expired']);
        $this->get(route('financial-plans.create'))->assertOk()->assertViewHas('hasCurrentYearAidatPlan', false);
        $this->post(route('financial-plans.select-type'), ['type' => 'aidat'])
            ->assertRedirect(route('due-plans.index', ['year' => 2027, 'new' => 1]));
        $this->travelBack();
    }

    public function test_expense_confirmation_defers_writes_and_can_return_to_form(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('expense-plans.store'), $payload)->assertOk()->assertViewIs('expense-plans.confirm');
        $this->assertSame(0, ExpensePlan::count());
        $this->assertSame(0, Expense::count());
        $this->assertSame(3, $preview->viewData('summary')['count']);
        $this->assertSame(3750.0, (float) $preview->viewData('summary')['amount']);
        $this->post(route('expense-plans.confirm'), [
            'confirmation_token' => $preview->viewData('token'), 'return_to_form' => 1,
        ])->assertRedirect(route('expense-plans.create'))->assertSessionHasInput('name', $payload['name']);
        $this->assertSame(0, ExpensePlan::count());
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertStatus(419);

        $preview = $this->post(route('expense-plans.store'), $payload)->assertOk();
        $token = $preview->viewData('token');
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $token, 'amount' => 1])->assertRedirect();
        $plan = ExpensePlan::firstOrFail();
        $this->assertSame('1250.00', $plan->amount);
        $this->assertSame(3, $plan->expenses()->count());
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $token])->assertStatus(419);
        $this->assertSame(1, ExpensePlan::count());
        $this->travelBack();
    }

    public function test_scheduled_expense_summary_creates_past_and_persists_current_skip(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [...$this->expensePlanPayload($apartment), 'generation_mode' => 'on_schedule'];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('expense-plans.store'), $payload)->assertOk();
        $this->assertTrue($preview->viewData('summary')['current_eligible']);
        $this->assertSame(9, $preview->viewData('summary')['past_count']);
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])
            ->assertOk()->assertViewIs('expense-plans.confirm');
        $this->assertSame(0, ExpensePlan::count());
        $token = session('expense_plan_confirmation.token');
        $preview = $this->post(route('expense-plans.confirm'), [
            'confirmation_token' => $token, 'include_past_periods' => 1,
            'current_month_action' => 'skip', 'update_summary' => 1,
        ])->assertOk();
        $this->assertSame(9, $preview->viewData('summary')['count']);
        $this->assertSame(0, ExpensePlan::count());
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertRedirect();
        $plan = ExpensePlan::firstOrFail();
        $this->assertSame(['2026-10'], $plan->skipped_periods);
        $this->assertSame(9, $plan->expenses()->count());
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(9, $plan->expenses()->count());
        $this->travelTo(\Carbon\Carbon::parse('2026-11-05 12:00:00'));
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(10, $plan->expenses()->count());
        $this->travelBack();
    }

    public function test_expense_summary_rechecks_existing_records_and_changed_plan_before_confirm(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $plan = ExpensePlan::create([...$payload, 'apartment_id' => $apartment->id]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->put(route('expense-plans.update', $plan), [...$payload, 'amount' => 2000])->assertOk();
        $this->assertSame('1250.00', $plan->fresh()->amount);
        app(ExpensePlanGenerator::class)->generateForPeriod($plan, '2026-10');
        $plan->update(['name' => 'Başka oturumda değişti']);
        $updated = $this->post(route('expense-plans.confirm'), ['confirmation_token' => $preview->viewData('token')])->assertOk();
        $this->assertTrue($updated->viewData('changed'));
        $this->assertSame(2, $updated->viewData('summary')['count']);
        $this->assertSame(1, $updated->viewData('summary')['existing']);
        $this->assertSame('1250.00', $plan->fresh()->amount);
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $this->assertSame('2000.00', $plan->fresh()->amount);
        $this->assertSame(3, $plan->expenses()->count());
        $this->assertSame('1250.00', $plan->expenses()->orderBy('expense_date')->firstOrFail()->amount);
        $this->travelBack();
    }

    public function test_scheduled_expense_current_create_requires_refreshed_summary(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [...$this->expensePlanPayload($apartment), 'generation_mode' => 'on_schedule'];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $preview = $this->post(route('expense-plans.store'), $payload)->assertOk();
        $updated = $this->post(route('expense-plans.confirm'), [
            'confirmation_token' => $preview->viewData('token'), 'current_month_action' => 'create',
        ])->assertOk();
        $this->assertSame(1, $updated->viewData('summary')['count']);
        $this->assertSame(0, ExpensePlan::count());
        $this->post(route('expense-plans.confirm'), ['confirmation_token' => $updated->viewData('token')])->assertRedirect();
        $plan = ExpensePlan::firstOrFail();
        $this->assertSame(1, $plan->expenses()->count());
        $this->assertSame('2026-10-05', $plan->expenses()->firstOrFail()->expense_date->toDateString());
        $this->assertSame([], $plan->skipped_periods);
        $this->travelBack();
    }

    public function test_expense_plan_bulk_generation_skips_past_and_existing_periods(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->saveExpensePlan(route('expense-plans.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        $plan = ExpensePlan::where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertSame(3, $plan->expenses()->count());
        $expense = $plan->expenses()->orderBy('expense_date')->firstOrFail();
        $this->assertSame('2026-10-05', $expense->expense_date->toDateString());
        $this->assertSame('2026-10-20', $expense->due_date->toDateString());
        $this->assertSame('2026 Ekim - Elektrik ortak alan tüketimi', $expense->description);
        $this->assertFalse($expense->is_paid);

        $payload['amount'] = 2000;
        $this->saveExpensePlanUpdate($plan, $payload)
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($message) => str_contains($message, '3 mevcut'));
        $this->assertSame(3, $plan->expenses()->count());
        $this->assertSame('1250.00', $expense->fresh()->amount);

        $payload['include_past_periods'] = 1;
        $this->saveExpensePlanUpdate($plan, $payload)->assertSessionHasNoErrors();
        $this->assertSame(12, $plan->expenses()->count());
        $this->assertSame(12, \App\Models\AccountTransaction::where('account_id', $payload['account_id'])->count());
        $this->assertSame('2000.00', $plan->expenses()->orderBy('expense_date')->firstOrFail()->amount);
        $this->get(route('expense-plans.show', $plan))->assertOk()->assertSee('Tümünü şimdi oluştur');
        $this->travelBack();
    }

    public function test_expense_plan_bulk_validation_and_passive_plan(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->saveExpensePlan(route('expense-plans.store'), [...$payload, 'end_date' => ''])
            ->assertSessionHasErrors('end_date');
        $this->assertSame(0, ExpensePlan::count());
        $this->saveExpensePlan(route('expense-plans.store'), [...$payload, 'generation_mode' => 'invalid'])
            ->assertSessionHasErrors('generation_mode');
        $this->saveExpensePlan(route('expense-plans.store'), [...$payload, 'is_active' => 0, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $plan = ExpensePlan::firstOrFail();
        $this->assertSame(0, $plan->expenses()->count());
        $this->saveExpensePlanUpdate($plan, [...$payload, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(12, $plan->expenses()->count());
    }

    public function test_scheduled_command_only_generates_scheduled_expense_plans(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $scheduled = ExpensePlan::create([
            ...$payload,
            'apartment_id' => $apartment->id,
            'generation_mode' => ExpensePlan::GENERATION_ON_SCHEDULE,
        ]);
        $bulk = ExpensePlan::create([...$payload, 'apartment_id' => $apartment->id]);
        $this->artisan('financial-plans:auto-generate')->assertExitCode(0);
        $this->assertSame(1, $scheduled->expenses()->count());
        $this->assertSame(0, $bulk->expenses()->count());
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->saveExpensePlanUpdate($scheduled, [...$payload, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(12, $scheduled->expenses()->count());
        $this->travelBack();
    }
}
