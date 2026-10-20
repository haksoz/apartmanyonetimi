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
            ->post(route('expense-plans.store'), [
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
            ->put(route('expense-plans.update', $suffixPlan), ['name' => ''])
            ->assertSessionHasErrors('name')
            ->assertRedirect(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]));
        $errorResponse = $this->get(route('expense-plans.show', ['expensePlan' => $suffixPlan, 'edit' => 1]))->assertOk();
        $this->assertTrue(str_contains($errorResponse->getContent(), 'id="expense-plan-form"'), 'Validation errors must keep form open.');

        $this->put(route('expense-plans.update', $suffixPlan), [
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

        $this->put(route('expense-plans.update', $suffixPlan), [
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
            $this->put(route('expense-plans.update', $suffixPlan), [
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

        $this->get(route('assessment-plans.show', $plan))->assertOk()
            ->assertSee('Plan Bilgileri')->assertSee('Düzenle')
            ->assertDontSee('name="name"', false);
        $this->get(route('assessment-plans.show', ['assessmentPlan' => $plan, 'edit' => 1]))
            ->assertOk()->assertSee('name="name"', false)->assertSee('İptal');
        $this->put(route('assessment-plans.update', $plan), [
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
            ->put(route('assessment-plans.update', $plan), [
                'name' => 'Yetkisiz değişiklik', 'generate_day' => 1, 'is_active' => 1,
            ])->assertNotFound();
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

    public function test_aidat_bulk_generation_uses_the_past_periods_form_choice(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = [
            'plan_year' => 2026,
            'monthly_amount' => 1000,
            'distribution_type' => 'equal',
            'target_audience' => 'owner_only',
            'due_day' => 5,
            'generate_day' => 1,
            'generation_mode' => DuePlan::GENERATION_CREATE_ALL_NOW,
            'is_active' => 1,
        ];
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->get(route('due-plans.index', ['year' => 2026]))
            ->assertOk()->assertSee('Geçmiş aylara ait aidatları da oluştur')
            ->assertSee('id="include_past_periods"', false);

        $this->post(route('due-plans.store'), [...$payload, 'is_active' => 0, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, Due::where('apartment_id', $apartment->id)->count());

        // An unchecked or omitted field excludes past months without a second prompt.
        $this->post(route('due-plans.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('prompt_past_periods_plan_id')
            ->assertRedirect(route('due-plans.index', ['year' => 2026]));
        $this->assertSame(6, Due::where('apartment_id', $apartment->id)->count());
        $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 0])
            ->assertSessionHasNoErrors()->assertSessionMissing('prompt_past_periods_plan_id');
        $this->assertSame(6, Due::where('apartment_id', $apartment->id)->count());

        $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors()->assertSessionMissing('prompt_past_periods_plan_id');
        $this->assertSame(24, Due::where('apartment_id', $apartment->id)->count());
        $this->post(route('due-plans.store'), [...$payload, 'include_past_periods' => 1])->assertSessionHasNoErrors();
        $this->assertSame(24, Due::where('apartment_id', $apartment->id)->count());
        $this->get(route('due-plans.index', ['year' => 2026]))->assertOk()
            ->assertDontSee('Geçmişi de oluştur')->assertDontSee('Geçmişi atla');
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
            ->assertSee('2026 yılı aidat planı zaten oluşturuldu.')
            ->assertSee('cursor-not-allowed bg-slate-50 opacity-50', false)
            ->assertSee('value="aidat" class="mt-1" required disabled', false)
            ->assertDontSee('Mevcut aidat planını düzenle');
        $this->post(route('financial-plans.select-type'), ['type' => 'aidat'])
            ->assertRedirect(route('financial-plans.create'))
            ->assertSessionHasErrors('type');
        $this->post(route('financial-plans.select-type'), ['type' => 'expense'])
            ->assertRedirect(route('expense-plans.create'));

        DuePlan::where('plan_year', 2026)->update(['is_active' => false]);
        $this->get(route('financial-plans.create'))->assertViewHas('hasCurrentYearAidatPlan', true);
        DuePlan::where('plan_year', 2026)->update(['is_active' => true]);

        $this->travelTo(\Carbon\Carbon::parse('2027-01-01 00:00:00'));
        $rows = $this->get(route('financial-plans.index'))->assertOk()->viewData('plans')->keyBy('period_label');
        $this->assertTrue($rows['2026']['is_expired']);
        $this->get(route('financial-plans.create'))->assertOk()->assertViewHas('hasCurrentYearAidatPlan', false);
        $this->post(route('financial-plans.select-type'), ['type' => 'aidat'])
            ->assertRedirect(route('due-plans.index', ['year' => 2027]));
        $this->travelBack();
    }

    public function test_expense_plan_bulk_generation_skips_past_and_existing_periods(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-20 12:00:00'));
        [$user, $apartment] = $this->setupApartment();
        $payload = $this->expensePlanPayload($apartment);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->post(route('expense-plans.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        $plan = ExpensePlan::where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertSame(3, $plan->expenses()->count());
        $expense = $plan->expenses()->orderBy('expense_date')->firstOrFail();
        $this->assertSame('2026-10-05', $expense->expense_date->toDateString());
        $this->assertSame('2026-10-20', $expense->due_date->toDateString());
        $this->assertSame('2026 Ekim - Elektrik ortak alan tüketimi', $expense->description);
        $this->assertFalse($expense->is_paid);

        $payload['amount'] = 2000;
        $this->put(route('expense-plans.update', $plan), $payload)
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($message) => str_contains($message, '3 mevcut'));
        $this->assertSame(3, $plan->expenses()->count());
        $this->assertSame('1250.00', $expense->fresh()->amount);

        $payload['include_past_periods'] = 1;
        $this->put(route('expense-plans.update', $plan), $payload)->assertSessionHasNoErrors();
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
            ->post(route('expense-plans.store'), [...$payload, 'end_date' => ''])
            ->assertSessionHasErrors('end_date');
        $this->assertSame(0, ExpensePlan::count());
        $this->post(route('expense-plans.store'), [...$payload, 'generation_mode' => 'invalid'])
            ->assertSessionHasErrors('generation_mode');
        $this->post(route('expense-plans.store'), [...$payload, 'is_active' => 0, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $plan = ExpensePlan::firstOrFail();
        $this->assertSame(0, $plan->expenses()->count());
        $this->put(route('expense-plans.update', $plan), [...$payload, 'include_past_periods' => 1])
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
            ->put(route('expense-plans.update', $scheduled), [...$payload, 'include_past_periods' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(12, $scheduled->expenses()->count());
        $this->travelBack();
    }
}
