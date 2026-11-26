<?php

namespace Tests\Feature;

use App\Http\Controllers\DuePlanController;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentInstallmentLine;
use App\Models\AssessmentPlan;
use App\Models\Category;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\PlanReminderDismissal;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use App\Support\MissingPlanReminders;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanReminderTest extends TestCase
{
    use RefreshDatabase;

    private function setupCase(): array
    {
        $this->travelTo(Carbon::parse('2026-11-20 12:00:00'));
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 2, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        Category::createDefaultsFor($apartment->id);
        $apartment->refresh();
        $this->grantPaidFeatures($apartment);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        foreach ([1, 2] as $no) {
            $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $no]);
            $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'owner', 'name' => 'Malik '.$no, 'is_active' => true]);
            $unit->update(['owner_account_id' => $account->id]);
        }
        $plan = DuePlan::create(['apartment_id' => $apartment->id, 'category_id' => $apartment->default_aidat_category_id, 'name' => 'Aidat Kararı',
            'plan_year' => 2026, 'amount_type' => 'monthly', 'monthly_amount' => 100, 'distribution_type' => 'equal', 'due_day' => 20, 'generate_day' => 1, 'is_active' => true]);
        app(DuePlanController::class)->createDuesForPeriod($plan, '2026-11');
        $due = Due::first();
        $due->transactions()->delete();
        $due->delete();

        return [$user, $apartment, $plan];
    }

    private function notices(Apartment $apartment, User $user): array
    {
        return app(MissingPlanReminders::class)->forApartment($apartment, $user);
    }

    public function test_aidat_dismissal_persists_without_changing_debts_or_plan_details(): void
    {
        [$user, $apartment, $plan] = $this->setupCase();
        $this->assertCount(1, $this->notices($apartment, $user));
        $data = ['plan_type' => 'aidat', 'plan_id' => $plan->id, 'period' => '2026-11'];
        $this->post(route('plan-reminders.dismiss'), $data)->assertRedirect(route('dashboard'));
        $this->post(route('plan-reminders.dismiss'), $data)->assertRedirect();
        $this->assertDatabaseCount('plan_reminder_dismissals', 1);
        $this->assertSame([], $this->notices($apartment->fresh(), $user->fresh()));
        $this->assertSame(1, Due::count());
        $this->get(route('due-plans.period-detail', ['duePlan' => $plan->id, 'period' => '2026-11']))->assertOk()->assertSee('Eksik Aidatları Oluştur');
        $other = User::factory()->create();
        $apartment->members()->attach($other->id, ['role' => 'owner', 'is_active' => true]);
        $this->assertCount(1, $this->notices($apartment, $other));
        $this->travelTo(Carbon::parse('2026-12-20 12:00:00'));
        app(DuePlanController::class)->createDuesForPeriod($plan, '2026-12');
        $december = Due::forMonth('2026-12')->first();
        $december->transactions()->delete();
        $december->delete();
        $notices = $this->notices($apartment, $user);
        $this->assertCount(1, $notices);
        $this->assertSame('2026-12', $notices[0]['period']);
    }

    public function test_assessment_missing_installments_have_their_own_plan_period_dismissal(): void
    {
        [$user, $apartment, $aidat] = $this->setupCase();
        $category = Category::where('apartment_id', $apartment->id)->where('allow_malik_payi', true)->firstOrFail();
        $plan = AssessmentPlan::create(['apartment_id' => $apartment->id, 'name' => 'Asansör Yenileme', 'category_id' => $category->id,
            'total_amount' => 100, 'distribution_type' => 'equal', 'installment_count' => 1, 'generation_mode' => 'on_schedule', 'generate_day' => 1, 'is_active' => true]);
        $installment = AssessmentInstallment::create(['assessment_plan_id' => $plan->id, 'sequence' => 1, 'period' => '2026-11', 'due_date' => '2026-11-28', 'status' => 'generated']);
        foreach (Unit::where('apartment_id', $apartment->id)->get() as $unit) {
            AssessmentInstallmentLine::create(['assessment_plan_id' => $plan->id, 'assessment_installment_id' => $installment->id, 'unit_id' => $unit->id, 'amount' => 50]);
        }
        $notices = $this->notices($apartment, $user);
        $this->assertCount(2, $notices);
        $this->assertSame('assessment', $notices[1]['type']);
        $this->assertSame(2, $notices[1]['missing']);
        $this->post(route('plan-reminders.dismiss'), ['plan_type' => 'assessment', 'plan_id' => $plan->id, 'period' => '2026-11'])->assertRedirect();
        $remaining = $this->notices($apartment, $user);
        $this->assertCount(1, $remaining);
        $this->assertSame('aidat', $remaining[0]['type']);
        PlanReminderDismissal::query()->delete();
        $installment->update(['status' => 'cancelled']);
        $this->assertCount(1, $this->notices($apartment, $user));
        $installment->update(['status' => 'pending']);
        $this->assertCount(1, $this->notices($apartment, $user));
        $this->assertSame(1, Due::count());
    }

    public function test_dismissal_is_apartment_scoped_and_requires_owner_role(): void
    {
        [$user, $apartment, $plan] = $this->setupCase();
        $other = Apartment::create(['user_id' => $user->id, 'name' => 'Other', 'unit_count' => 1]);
        $apartment->members()->updateExistingPivot($user->id, ['role' => 'member']);
        $data = ['plan_type' => 'aidat', 'plan_id' => $plan->id, 'period' => '2026-11'];
        $this->post(route('plan-reminders.dismiss'), $data)->assertForbidden();
        $apartment->members()->updateExistingPivot($user->id, ['role' => 'owner']);
        $other->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $this->withSession([CurrentApartment::SESSION_KEY => $other->id])->post(route('plan-reminders.dismiss'), $data)->assertNotFound();
        $this->assertDatabaseCount('plan_reminder_dismissals', 0);
    }
}
