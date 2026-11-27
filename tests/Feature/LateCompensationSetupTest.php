<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\AssessmentPlan;
use App\Models\Category;
use App\Models\Due;
use App\Models\DuePlan;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateCompensationSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_closed_by_default_and_in_production_even_if_enabled(): void
    {
        [$apartment] = $this->setupApartment();
        config(['late-compensation.preview' => false]);
        $this->get(route('apartments.index'))->assertOk()->assertDontSee('Aylık oran (%)');
        $this->savePolicy($apartment, ['is_active' => 1])->assertForbidden();

        config(['late-compensation.preview' => true]);
        $this->app->instance('env', 'production');
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->get(route('apartments.index'))->assertOk()->assertDontSee('Aylık oran (%)');
        $this->savePolicy($apartment, ['is_active' => 1])->assertForbidden();
        $this->assertDatabaseCount('late_compensation_policies', 0);
    }

    public function test_settings_record_dated_changes_and_never_update_existing_dues_or_another_apartment(): void
    {
        [$apartment, $account] = $this->setupApartment();
        $this->post(route('dues.store'), $this->individualPayload($account))->assertSessionHasNoErrors();
        $due = Due::sole();
        $this->get(route('subscriber.apartments.edit', $apartment))->assertOk()->assertDontSee('Aylık oran (%)');
        $this->get(route('apartments.index'))->assertOk()->assertSee('Bugünkü durum pasif');
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.125', 'starts_on' => '2026-04-14'])
            ->assertSessionHasErrors('late_compensation.starts_on');
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.125', 'starts_on' => '2026-04-15'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-04-20 12:00:00'));
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '3', 'effective_from' => '2026-04-19'])
            ->assertSessionHasErrors('late_compensation.effective_from');
        $this->savePolicy($apartment, ['is_active' => 0, 'monthly_rate' => '2.125', 'effective_from' => '2026-04-21'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '3', 'effective_from' => '2026-04-25'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '4', 'effective_from' => '2026-04-25'])
            ->assertSessionHasErrors('late_compensation.effective_from');
        $this->assertSame(['2.125000', '2.125000', '3.000000'], $apartment->lateCompensationPolicies()->orderBy('effective_from')->get()->pluck('monthly_rate')->all());
        $this->assertFalse($due->fresh()->late_compensation_applies);
        $this->assertSame('100.00', $due->fresh()->remaining_amount);
        $this->travelTo(Carbon::parse('2026-04-22 12:00:00'));
        $this->get(route('apartments.index'))->assertOk()->assertSee('Bugünkü durum pasif');
        $this->travelTo(Carbon::parse('2026-04-25 12:00:00'));
        $this->get(route('apartments.index'))->assertOk()->assertSee('Bugünkü durum aktif');

        $other = Apartment::factory()->create();
        $this->savePolicy($other, ['is_active' => 1, 'monthly_rate' => '3', 'starts_on' => '2026-04-25'])->assertNotFound();
        $this->assertFalse($other->lateCompensationPolicies()->exists());
    }

    public function test_active_policy_can_be_disabled_on_its_effective_day_without_changing_its_rate(): void
    {
        [$apartment] = $this->setupApartment();

        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.125', 'starts_on' => '2026-04-15'])
            ->assertSessionHasNoErrors();
        $this->savePolicy($apartment, ['is_active' => 0, 'monthly_rate' => '4'])
            ->assertRedirect(route('apartments.index'))->assertSessionHasNoErrors();

        $policy = $apartment->lateCompensationPolicies()->sole();
        $this->assertFalse($policy->is_active);
        $this->assertSame('2.125000', $policy->monthly_rate);
        $this->assertSame('2026-04-15', $policy->effective_from->toDateString());
        $this->get(route('apartments.index'))->assertOk()->assertSee('Bugünkü durum pasif');
    }

    public function test_manual_choice_uses_shared_scope_and_server_recorded_source(): void
    {
        [$apartment, $account] = $this->setupApartment();
        $page = route('dues.create', ['due_type' => 'aidat']);
        $input = $this->individualPayload($account);
        $this->get($page)->assertOk()->assertDontSee('Gecikme tazminatı uygulansın');
        $this->post(route('dues.store'), $input + ['late_compensation_applies' => 1])
            ->assertSessionHasErrors('late_compensation_applies');
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.5', 'starts_on' => '2026-04-15'])
            ->assertSessionHasNoErrors();
        $this->get($page)->assertOk()->assertSee('Gecikme tazminatı uygulansın');
        $this->post(route('dues.store'), $input + ['late_compensation_applies' => 1, 'production_source' => 'aidat_plan'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dues', ['account_id' => $account->id, 'production_source' => 'individual', 'late_compensation_applies' => true]);

        config(['late-compensation.excluded_due_types.individual' => ['*']]);
        $this->get($page)->assertOk()->assertDontSee('Gecikme tazminatı uygulansın');
        $this->post(route('dues.store'), $input + ['late_compensation_applies' => 1])->assertSessionHasErrors('late_compensation_applies');
        $this->assertDatabaseCount('dues', 1);
        $this->assertTrue(Due::sole()->late_compensation_applies);
        $this->post(route('dues.store'), [
            'source_type' => 'fixed', 'distribution_type' => 'equal', 'due_type' => 'aidat',
            'category_id' => $apartment->fresh()->default_aidat_category_id, 'fixed_amount' => 125, 'due_date' => '2026-05-10', 'created_at_manual' => '2026-05-01',
            'late_compensation_applies' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dues', ['account_id' => $account->id, 'production_source' => 'fixed_batch', 'late_compensation_applies' => true]);
    }

    public function test_aidat_plan_preference_survives_passive_edit_and_is_copied_only_to_new_dues(): void
    {
        [$apartment] = $this->setupApartment();
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.5', 'starts_on' => '2026-04-15'])->assertSessionHasNoErrors();
        $this->assertSame(1, $apartment->lateCompensationPolicies()->count());
        $this->assertTrue(app(\App\Support\LateCompensationScope::class)->selectionAvailable($apartment->fresh()));
        $this->get(route('due-plans.index', ['year' => 2026, 'new' => 1, 'edit' => 1]))
            ->assertOk()->assertSee('Gecikme tazminatı uygulansın');
        $input = [
            'plan_year' => 2026, 'monthly_amount' => 600, 'distribution_type' => 'equal',
            'due_day' => 10, 'generate_day' => 5, 'is_active' => 1,
        ];

        $this->saveAidatPlan($input + ['late_compensation_applies' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $plan = DuePlan::sole();
        $this->assertTrue($plan->late_compensation_applies);
        $this->savePolicy($apartment, ['is_active' => 0, 'monthly_rate' => '2.5', 'effective_from' => '2026-05-01'])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-05-15 12:00:00'));
        $this->get(route('due-plans.index', ['year' => 2026, 'edit' => 1, 'plan_id' => $plan->id]))
            ->assertOk()->assertDontSee('Gecikme tazminatı uygulansın');
        $this->saveAidatPlan($input + ['plan_id' => $plan->id, 'current_month_action' => 'create'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($plan->fresh()->late_compensation_applies);
        $may = Due::query()->where('production_source', 'aidat_plan')->whereDate('due_date', '2026-05-10')->sole();
        $this->assertTrue($may->late_compensation_applies);

        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.5', 'effective_from' => '2026-06-01'])->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $this->saveAidatPlan($input + ['plan_id' => $plan->id, 'late_compensation_applies' => 0, 'current_month_action' => 'create'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($plan->fresh()->late_compensation_applies);
        $june = Due::query()->where('production_source', 'aidat_plan')->whereDate('due_date', '2026-06-10')->sole();
        $this->assertFalse($june->late_compensation_applies);
        $this->assertTrue($may->fresh()->late_compensation_applies);
    }

    public function test_assessment_confirmation_copies_the_preference_to_installment_debts(): void
    {
        [$apartment] = $this->setupApartment();
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.5', 'starts_on' => '2026-04-15'])->assertSessionHasNoErrors();
        $this->assertSame(1, $apartment->lateCompensationPolicies()->count());
        $this->assertTrue(app(\App\Support\LateCompensationScope::class)->selectionAvailable($apartment->fresh()));
        $this->get(route('assessment-plans.create'))->assertOk()->assertSee('Gecikme tazminatı uygulansın');
        $this->saveAssessmentPlan(route('assessment-plans.store'), [
            'due_type' => 'malik_payi', 'name' => 'Çatı Yenileme', 'total_amount' => 1000, 'distribution_type' => 'equal',
            'installment_count' => 2, 'start_period' => '2026-05', 'due_day' => 10, 'due_interval_months' => 1,
            'generation_mode' => 'create_all_now', 'generate_day' => 5, 'is_active' => 1, 'late_compensation_applies' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(AssessmentPlan::sole()->late_compensation_applies);
        $this->assertSame(2, Due::query()->where('production_source', 'assessment_plan')->where('late_compensation_applies', true)->count());
        $dueUrl = route('dues.show', Due::query()->oldest('id')->firstOrFail());
        $this->get($dueUrl)->assertOk()
            ->assertSee('Referans')
            ->assertSee('Taksitli Borç Planı')
            ->assertDontSee('Üretim kaynağı')
            ->assertDontSee('Plan adı');

        auth()->user()->forceFill(['is_test_customer' => true])->save();
        $this->get($dueUrl)
            ->assertOk()
            ->assertSee('Üretim kaynağı')
            ->assertSee('Taksitli Borç Planı')
            ->assertSee('Plan adı')
            ->assertSee(route('assessment-plans.show', AssessmentPlan::sole()))
            ->assertSee('Tümünü şimdi oluştur')
            ->assertSee('1 / 2')
            ->assertSee('Gecikme Tazminatı kapsamı');
    }

    public function test_new_imported_due_records_source_and_explicit_late_compensation_choice(): void
    {
        [$apartment, $account] = $this->setupApartment();
        $this->savePolicy($apartment, ['is_active' => 1, 'monthly_rate' => '2.5', 'starts_on' => '2026-04-15'])->assertSessionHasNoErrors();
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Tarih', 'Hesap Adı', 'Daire No', 'Kategori', 'Açıklama', 'Alacak', 'Borç', 'Borç Türü'],
            ['01.01.2026', $account->name, '1', 'Genel Aidat', 'İçe aktarılan borç', 0, 500, 'aidat'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'aidatcep-import-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
            $file = new \Illuminate\Http\UploadedFile($path, 'import.xlsx', test: true);
            $this->post(route('accounts.bulk-import-preview'), ['file' => $file])->assertRedirect();
        } finally {
            unlink($path);
        }
        $this->get(route('accounts.bulk-import-preview-page'))->assertOk()->assertSee('Gecikme tazminatı uygulansın');
        $key = $account->name.'|1';
        $this->post(route('accounts.bulk-import-confirm'), [
            'account_types' => [$key => 'owner'],
            'account_mapping' => [$key => $account->id],
            'due_categories' => [2 => (string) $apartment->fresh()->default_aidat_category_id],
            'late_compensation_rows' => [2 => 1],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('dues', ['apartment_id' => $apartment->id, 'production_source' => 'import', 'late_compensation_applies' => true]);
    }

    private function setupApartment(): array
    {
        $this->travelTo(Carbon::parse('2026-04-15 12:00:00'));
        config(['late-compensation.preview' => true]);
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 1, 'address' => 'Test adresi']);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Genel Aidat', 'is_active' => true,
            'allow_aidat' => true, 'type' => Category::TYPE_INCOME]);
        $apartment->update(['default_aidat_category_id' => $category->id]);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => Account::TYPE_OWNER,
            'unit_id' => $unit->id, 'name' => 'Kat maliki', 'is_active' => true]);
        $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return [$apartment, $account];
    }

    private function individualPayload(Account $account): array
    {
        return [
            'source_type' => 'individual', 'distribution_type' => 'individual', 'due_type' => 'aidat',
            'due_date' => '2026-04-30', 'created_at_manual' => '2026-04-15',
            'account_id' => $account->id, 'individual_amount' => 100, 'category_id' => $account->apartment->default_aidat_category_id,
        ];
    }

    private function savePolicy(Apartment $apartment, array $policy): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('apartments.late-compensation.update', $apartment), [
            'late_compensation' => $policy,
        ]);
    }
}
