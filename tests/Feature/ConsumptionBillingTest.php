<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\ConsumptionPeriod;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Expense;
use App\Models\Unit;
use App\Models\UnitConsumption;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsumptionBillingTest extends TestCase
{
    use RefreshDatabase;

    private function setupCase(array $weights = ['1', '2', '0']): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Tüketim Borçlandırma', 'unit_count' => count($weights)]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $defaultCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Genel Aidat', 'allow_aidat' => true, 'is_active' => true]);
        $apartment->update(['default_aidat_category_id' => $defaultCategory->id]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Su Tüketimi', 'allow_aidat' => true, 'allow_malik_payi' => true, 'is_active' => true]);
        $period = ConsumptionPeriod::create(['apartment_id' => $apartment->id, 'consumption_type' => 'cold_water', 'start_date' => '2026-09-18', 'end_date' => '2026-10-23', 'method' => 'direct', 'measurement_unit' => 'm³']);
        foreach ($weights as $i => $weight) {
            $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) ($i + 1)]);
            $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'name' => 'Malik '.($i + 1), 'type' => 'owner', 'is_active' => true]);
            $unit->update(['owner_account_id' => $account->id]);
            if ($weight !== null) {
                UnitConsumption::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'consumption_period_id' => $period->id, 'consumption_type' => 'cold_water', 'method' => 'direct', 'measurement_unit' => 'm³', 'value' => $weight, 'consumption' => $weight]);
            }
        }
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return [$apartment, $period, ['source_type' => 'manual', 'distribution_type' => 'consumption', 'due_type' => 'aidat', 'category_id' => $category->id,
            'source_amount' => '100.00', 'created_at_manual' => '2026-11-01', 'due_date' => '2026-11-30', 'description' => 'Su Tüketimi',
            'consumption_type' => 'cold_water', 'consumption_period_id' => $period->id]];
    }

    private function confirm(string $token)
    {
        return $this->post(route('dues.consumption.confirm'), ['confirmation_token' => $token]);
    }

    private function batchPage()
    {
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', fn ($date, $format) => $date ? substr($date, 0, 7) : null, 2);

        return $this->get(route('dues.batch.create', ['due_type' => 'aidat', 'source_type' => 'manual']));
    }

    public function test_preview_is_read_only_and_confirmation_creates_exact_debts_and_ledger(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $preview = $this->post(route('dues.store'), $input);
        $preview->assertOk()->assertSee('Borçlandırma Önizlemesi')->assertSee('Sıfır tüketim');
        $this->assertDatabaseCount('due_batches', 0);
        $this->assertDatabaseCount('dues', 0);
        $this->assertTrue(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $this->assertTrue($preview->viewData('summary')['can_confirm']);
        $this->assertSame([3333, 6667, 0], array_column($preview->viewData('summary')['rows'], 'amount_cents'));
        $token = $preview->viewData('token');
        $this->confirm($token)->assertRedirect();
        $this->assertDatabaseCount('due_batches', 1);
        $this->assertDatabaseCount('dues', 2);
        $this->assertEquals(100, Due::sum('amount'));
        $this->assertEquals(100, AccountTransaction::sum('amount'));
        $this->assertDatabaseHas('due_batches', ['consumption_period_id' => $period->id, 'distribution_type' => 'consumption']);
        $this->assertFalse(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $this->confirm($token)->assertStatus(419);
        $this->post(route('dues.store'), $input)->assertSessionHasErrors('consumption_period_id');
    }

    public function test_late_compensation_choice_is_confirmed_and_preserved_when_repairing_a_new_batch(): void
    {
        [$apartment, , $input] = $this->setupCase();
        config(['late-compensation.preview' => true]);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 12:00:00'));
        $apartment->update(['late_compensation_starts_on' => '2026-11-01']);
        $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-01', 'is_active' => true, 'monthly_rate' => '2.5',
        ]);

        $this->batchPage()->assertOk()->assertSee('Gecikme tazminatı uygulansın');
        $preview = $this->post(route('dues.store'), $input + ['late_compensation_applies' => 1])->assertOk();
        $this->confirm($preview->viewData('token'))->assertRedirect()->assertSessionHasNoErrors();
        $batch = DueBatch::sole();
        $this->assertSame(2, $batch->dues()->where('production_source', 'consumption')->where('late_compensation_applies', true)->count());
        $batch->dues()->firstOrFail()->delete();

        $apartment->lateCompensationPolicies()->create([
            'effective_from' => '2026-11-02', 'is_active' => false, 'monthly_rate' => '2.5',
        ]);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03 12:00:00'));
        $tracking = app(\App\Services\ConsumptionBatchTracking::class);
        $this->assertSame(1, $tracking->repair($batch, $tracking->summary($batch)));
        $this->assertSame(2, $batch->dues()->where('production_source', 'consumption')->where('late_compensation_applies', true)->count());
    }

    public function test_missing_readings_and_all_zero_consumption_block_confirmation(): void
    {
        [$apartment, $period, $input] = $this->setupCase(['1', null, '0']);
        $preview = $this->post(route('dues.store'), $input);
        $preview->assertOk()->assertSee('Okuma eksik');
        $this->assertFalse($preview->viewData('summary')['can_confirm']);
        $this->confirm($preview->viewData('token'))->assertOk();
        $this->assertDatabaseCount('dues', 0);
        UnitConsumption::where('apartment_id', $apartment->id)->update(['consumption' => 0]);
        $preview = $this->post(route('dues.store'), $input);
        $preview->assertOk();
        $this->assertFalse($preview->viewData('summary')['can_confirm']);
    }

    public function test_changed_consumption_refreshes_preview_before_confirmation(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        UnitConsumption::orderBy('id')->first()->update(['consumption' => '4']);
        $refreshed = $this->confirm($preview->viewData('token'));
        $refreshed->assertOk()->assertViewHas('changed', true);
        $this->assertDatabaseCount('dues', 0);
        $this->confirm($refreshed->viewData('token'))->assertRedirect();
        $this->assertEquals(100, Due::sum('amount'));
    }

    public function test_used_periods_are_hidden_and_cancellation_releases_only_fully_cancelled_batch(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $this->assertTrue($this->batchPage()->assertOk()->viewData('consumptionPeriods')->contains('id', $period->id));
        $preview = $this->post(route('dues.store'), $input);
        $this->confirm($preview->viewData('token'))->assertRedirect();
        $batch = DueBatch::first();
        $this->assertFalse($this->batchPage()->assertOk()->viewData('consumptionPeriods')->contains('id', $period->id));
        $this->get(route('meters.history.detail', $period))->assertOk()->assertSee('Borçlandırmayı Görüntüle');
        Due::first()->update(['status' => 'partial']);
        $this->post(route('dues.consumption.cancel', $batch))->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('dues', 2);
        Due::query()->update(['status' => 'unpaid']);
        $this->delete(route('dues.destroy', Due::first()))->assertRedirect();
        $this->assertFalse(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $this->post(route('dues.consumption.cancel', $batch))->assertRedirect(route('meters.history.detail', $period));
        $this->assertEquals(0, Due::count());
        $this->assertEquals(0, AccountTransaction::count());
        $this->assertTrue(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $newPreview = $this->post(route('dues.store'), $input)->assertOk();
        $this->confirm($newPreview->viewData('token'))->assertRedirect();
        $this->assertDatabaseCount('due_batches', 2);
    }

    public function test_apartment_type_and_confirmation_input_isolation(): void
    {
        [$other, $foreign, $foreignInput] = $this->setupCase();
        [$apartment, $period, $input] = $this->setupCase();
        $this->post(route('dues.store'), [...$input, 'consumption_period_id' => $foreign->id])->assertSessionHasErrors('consumption_period_id');
        $this->post(route('dues.store'), [...$input, 'consumption_type' => 'heating'])->assertNotFound();
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        $this->post(route('dues.consumption.confirm'), ['confirmation_token' => $preview->viewData('token'), 'source_amount' => 1, 'consumption_period_id' => $foreign->id])->assertRedirect();
        $this->assertEquals(100, Due::sum('amount'));
        $this->assertDatabaseHas('due_batches', ['apartment_id' => $apartment->id, 'consumption_period_id' => $period->id]);
    }

    public function test_member_cannot_preview_confirm_or_cancel(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $apartment->members()->updateExistingPivot(auth()->id(), ['role' => 'member']);
        $this->post(route('dues.store'), $input)->assertForbidden();
        $this->post(route('dues.consumption.confirm'))->assertForbidden();
        $this->post(route('dues.consumption.cancel', 1))->assertForbidden();
    }

    public function test_zero_weight_never_gets_rounding_residue_and_total_is_exact(): void
    {
        [$apartment, $period, $input] = $this->setupCase(['1', '1', '1', '0']);
        $preview = $this->post(route('dues.store'), [...$input, 'source_amount' => '0.02'])->assertOk();
        $this->assertSame([1, 1, 0, 0], array_column($preview->viewData('summary')['rows'], 'amount_cents'));
        $this->confirm($preview->viewData('token'))->assertRedirect();
        $this->assertEquals(0.02, Due::sum('amount'));
        $this->assertEquals(2, Due::count());
    }

    public function test_missing_target_account_is_visible_and_return_to_form_does_not_use_period(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        Unit::orderBy('id')->first()->update(['owner_account_id' => null]);
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        $this->assertFalse($preview->viewData('summary')['can_confirm']);
        $preview->assertSee('uygun hesap bulunamadı');
        $this->post(route('dues.consumption.confirm'), ['confirmation_token' => $preview->viewData('token'), 'return_to_form' => 1])->assertRedirect();
        $this->assertTrue(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $this->assertDatabaseCount('dues', 0);
    }

    public function test_return_form_and_unit_labels_and_incompatible_sources(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $this->get(route('units.index'))->assertOk()->assertSee('Gider Dağıtım Katsayısı')->assertDontSee('Pay Çarpanı');
        $this->batchPage()->assertOk()->assertSee('Tüketime göre')->assertSee('Gider Dağıtım Katsayısına Göre');
        $this->post(route('dues.store'), [...$input, 'round_to_integer' => 1])->assertOk();
        $this->post(route('dues.store'), [...$input, 'source_type' => 'fixed', 'fixed_amount' => 100])->assertSessionHasErrors('distribution_type');
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        $this->travel(31)->minutes();
        $this->confirm($preview->viewData('token'))->assertStatus(419);
        $this->assertDatabaseCount('due_batches', 0);
    }

    public function test_second_user_preview_cannot_reuse_a_confirmed_period(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        $pending = session('consumption_confirmation');
        $this->confirm($preview->viewData('token'))->assertRedirect();
        $this->withSession(['consumption_confirmation' => $pending]);
        $this->confirm($pending['token'])->assertSessionHasErrors('consumption_period_id');
        $this->assertDatabaseCount('due_batches', 1);
        $this->assertEquals(100, Due::sum('amount'));
    }

    public function test_expense_consumption_shares_round_up_and_preserve_original_source_in_snapshot(): void
    {
        [$apartment, $period, $input] = $this->setupCase(['1', '2', '0']);
        $this->grantPaidFeatures($apartment);
        $expense = Expense::create(['apartment_id' => $apartment->id, 'description' => 'Su', 'amount' => '100.01', 'expense_date' => '2026-11-10']);
        $preview = $this->post(route('dues.store'), [...$input, 'source_type' => 'expenses', 'source_period' => '2026-11', 'selected_expense_ids' => (string) $expense->id, 'round_to_integer' => 1])->assertOk();
        $summary = $preview->viewData('summary');
        $this->assertSame([3400, 6700, 0], array_column($summary['rows'], 'amount_cents'));
        $this->assertSame(10001, $summary['amount_cents']);
        $this->assertSame(10100, $summary['billed_cents']);
        $this->confirm($preview->viewData('token'))->assertRedirect();
        $this->assertEquals(101, Due::sum('amount'));
        $this->assertEquals(101, AccountTransaction::sum('amount'));
        $this->assertEquals(101, DueBatch::first()->source_amount);
        $this->assertEquals(100.01, $expense->fresh()->amount);
        $this->assertSame(10001, DueBatch::first()->distribution_snapshot['amount_cents']);
    }

    public function test_upward_rounding_keeps_exact_integers_and_rounds_tiny_positive_shares(): void
    {
        [$apartment, $period, $input] = $this->setupCase(['1', '1', '0']);
        $preview = $this->post(route('dues.store'), [...$input, 'source_amount' => '2.00', 'round_to_integer' => 1])->assertOk();
        $this->assertSame([100, 100, 0], array_column($preview->viewData('summary')['rows'], 'amount_cents'));
        $tiny = $this->post(route('dues.store'), [...$input, 'source_amount' => '0.01', 'round_to_integer' => 1])->assertOk();
        $this->assertSame([100, 100, 0], array_column($tiny->viewData('summary')['rows'], 'amount_cents'));
        $this->confirm($tiny->viewData('token'))->assertRedirect();
        $this->assertEquals(2, Due::sum('amount'));
    }

    private function billedCase(): array
    {
        [$apartment, $period, $input] = $this->setupCase();
        $preview = $this->post(route('dues.store'), $input)->assertOk();
        $this->confirm($preview->viewData('token'))->assertRedirect();

        return [$apartment, $period, DueBatch::latest('id')->firstOrFail()];
    }

    private function repairPreview(DueBatch $batch)
    {
        return $this->post(route('dues.consumption.repair-preview', $batch));
    }

    private function repairConfirm(string $token)
    {
        return $this->post(route('dues.consumption.repair-confirm'), ['confirmation_token' => $token]);
    }

    public function test_missing_debt_is_displayed_and_repaired_from_original_snapshot(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        $deleted = Due::orderBy('id')->first();
        $survivor = Due::orderByDesc('id')->first();
        $survivor->update(['status' => 'paid', 'remaining_amount' => 0]);
        $survivorBefore = $survivor->fresh()->getAttributes();
        $this->delete(route('dues.destroy', $deleted))->assertRedirect();
        $detail = $this->get(route('meters.history.detail', $period))->assertOk()->assertSee('Eksik Borçları Tamamla')->assertSee('Eksik');
        $detail->assertSee('Borç Tutarı')->assertDontSee('Daire Bazında Borç Durumu')->assertDontSee('Ödendi')->assertDontSee('Kısmen ödendi');
        $this->assertSame(1, substr_count($detail->getContent(), '<table'));
        $this->assertEquals(3333, $detail->viewData('billingRows')->get($deleted->unit_id)['expected_cents']);
        $this->get(route('meters.history'))->assertOk()->assertSee('Dağıtım: 2/1');
        $state = $detail->viewData('tracking')[$batch->id];
        $this->assertSame(2, $state['expected_count']);
        $this->assertSame(1, $state['existing_count']);
        $this->assertSame(3333, $state['repair_cents']);
        $this->assertSame(6667, $state['actual_cents']);
        UnitConsumption::where('consumption_period_id', $period->id)->update(['consumption' => 999]);
        $originalAccount = Account::findOrFail($deleted->account_id);
        $originalAccount->update(['is_active' => false]);
        Unit::findOrFail($deleted->unit_id)->update(['owner_account_id' => null]);
        $preview = $this->repairPreview($batch)->assertOk();
        $this->assertTrue($preview->viewData('summary')['can_repair']);
        $this->assertEquals(1, Due::count());
        $token = $preview->viewData('token');
        $this->repairConfirm($token)->assertRedirect(route('meters.history.detail', $period));
        $restored = Due::where('unit_id', $deleted->unit_id)->firstOrFail();
        $this->assertNotEquals($deleted->id, $restored->id);
        $this->assertEquals($deleted->account_id, $restored->account_id);
        $this->assertSame('33.33', $restored->amount);
        $this->assertEquals($deleted->category_id, $restored->category_id);
        $this->assertEquals($deleted->due_date, $restored->due_date);
        $this->assertEquals($deleted->created_at_manual, $restored->created_at_manual);
        $this->assertEquals($survivorBefore, $survivor->fresh()->getAttributes());
        $this->assertEquals(100, AccountTransaction::sum('amount'));
        $this->get(route('meters.history.detail', $period))->assertOk()->assertSee('Tamamlandı')->assertDontSee('Eksik Borçları Tamamla');
        $this->get(route('meters.history'))->assertOk()->assertSee('Dağıtım: 2/2');
        $this->repairConfirm($token)->assertStatus(419);
    }

    public function test_deleting_all_debts_does_not_release_period_but_explicit_cancel_does(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        foreach (Due::all() as $due) {
            $this->delete(route('dues.destroy', $due))->assertRedirect();
        }
        $this->assertFalse(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $preview = $this->repairPreview($batch)->assertOk();
        $this->assertSame(2, $preview->viewData('summary')['missing_count']);
        $this->assertSame(10000, $preview->viewData('summary')['repair_cents']);
        $this->post(route('dues.consumption.cancel', $batch))->assertRedirect();
        $this->assertNotNull($batch->fresh()->consumption_cancelled_at);
        $this->get(route('meters.history'))->assertOk()->assertSee('Dağıtılmadı')->assertDontSee('Dağıtım: 2/0');
        $this->assertTrue(ConsumptionPeriod::unused()->whereKey($period->id)->exists());
        $this->repairConfirm($preview->viewData('token'))->assertOk()->assertViewHas('changed', true);
        $this->assertEquals(0, Due::count());
        $this->get(route('meters.history.detail', $period))->assertOk()->assertSee('İptal edildi')->assertDontSee('Eksik Borçları Tamamla');
    }

    public function test_repair_preview_refreshes_when_another_debt_is_deleted(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        $this->delete(route('dues.destroy', Due::first()))->assertRedirect();
        $preview = $this->repairPreview($batch)->assertOk();
        $this->delete(route('dues.destroy', Due::first()))->assertRedirect();
        $refreshed = $this->repairConfirm($preview->viewData('token'))->assertOk()->assertViewHas('changed', true);
        $this->assertEquals(0, Due::count());
        $this->assertSame(2, $refreshed->viewData('summary')['missing_count']);
        $this->repairConfirm($refreshed->viewData('token'))->assertRedirect();
        $this->assertEquals(2, Due::count());
        $this->assertEquals(100, Due::sum('amount'));
    }

    public function test_legacy_snapshot_can_recover_dates_and_category_from_deleted_debt(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        $snapshot = $batch->distribution_snapshot;
        unset($snapshot['debt_fields']);
        $batch->update(['distribution_snapshot' => $snapshot]);
        $deleted = Due::first();
        $this->delete(route('dues.destroy', $deleted))->assertRedirect();
        $preview = $this->repairPreview($batch)->assertOk();
        $this->assertTrue($preview->viewData('summary')['can_repair']);
        $this->repairConfirm($preview->viewData('token'))->assertRedirect();
        $this->assertEquals(100, Due::sum('amount'));
    }

    public function test_repair_is_blocked_when_original_account_is_missing_and_is_apartment_scoped(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        $deleted = Due::first();
        $this->delete(route('dues.destroy', $deleted))->assertRedirect();
        Account::findOrFail($deleted->account_id)->delete();
        $preview = $this->repairPreview($batch)->assertOk();
        $this->assertFalse($preview->viewData('summary')['can_repair']);
        $this->repairConfirm($preview->viewData('token'))->assertOk();
        $this->assertEquals(1, Due::count());
        $pending = session('consumption_repair');
        [$other] = $this->setupCase();
        $this->repairPreview($batch)->assertNotFound();
        $this->withSession(['consumption_repair' => $pending]);
        $this->repairConfirm($pending['token'])->assertStatus(419);
        $other->members()->updateExistingPivot(auth()->id(), ['role' => 'member']);
        $this->repairPreview($batch)->assertForbidden();
        $this->post(route('dues.consumption.repair-confirm'))->assertForbidden();
    }

    public function test_single_missing_debt_repair_is_scoped_and_confirmation_cannot_change_unit(): void
    {
        [$apartment, $period, $batch] = $this->billedCase();
        $dues = Due::orderBy('id')->get();
        foreach ($dues as $due) {
            $this->delete(route('dues.destroy', $due))->assertRedirect();
        }
        $this->get(route('meters.history.detail', $period))->assertOk()->assertSee('Tamamla');
        $preview = $this->post(route('dues.consumption.repair-preview', $batch), ['unit_id' => $dues[0]->unit_id])->assertOk();
        $this->assertSame(1, $preview->viewData('summary')['missing_count']);
        $this->assertSame(3333, $preview->viewData('summary')['repair_cents']);
        $this->assertCount(1, $preview->viewData('summary')['rows']);
        $this->post(route('dues.consumption.repair-confirm'), ['confirmation_token' => $preview->viewData('token'), 'unit_id' => $dues[1]->unit_id])->assertRedirect();
        $this->assertSame(1, Due::count());
        $this->assertDatabaseHas('dues', ['unit_id' => $dues[0]->unit_id, 'deleted_at' => null, 'amount' => '33.33']);
        $this->assertNull(Due::where('unit_id', $dues[1]->unit_id)->first());
        $this->post(route('dues.consumption.repair-preview', $batch), ['unit_id' => 999999])->assertNotFound();
        $alreadyPresent = $this->post(route('dues.consumption.repair-preview', $batch), ['unit_id' => $dues[0]->unit_id])->assertOk();
        $this->assertFalse($alreadyPresent->viewData('summary')['can_repair']);
        $remaining = $this->repairPreview($batch)->assertOk();
        $this->repairConfirm($remaining->viewData('token'))->assertRedirect();
        $this->assertEquals(100, Due::sum('amount'));
    }

    public function test_expense_period_picker_supports_ranges_and_fetches_month_boundaries(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $page = $this->batchPage()->assertOk();
        $page->assertSee('expense-source-period-open')->assertSee('name="source_period_end"', false)->assertSee('expense-source-period-dialog');
        $ids = [];
        foreach (['2025-11-30', '2025-12-01', '2026-01-15', '2026-02-28', '2026-03-01'] as $date) {
            $expense = Expense::create(['apartment_id' => $apartment->id, 'description' => $date, 'amount' => 10, 'expense_date' => $date]);
            $ids[$date] = $expense->id;
        }
        Expense::create(['apartment_id' => $apartment->id, 'description' => 'Opening', 'amount' => 999, 'expense_date' => '2026-01-10', 'is_opening' => true]);
        $response = $this->getJson(route('dues.expenses.by-period', ['period' => '2025-12', 'period_end' => '2026-02']));
        $response->assertOk()->assertJsonCount(3);
        $this->assertEquals([$ids['2025-12-01'], $ids['2026-01-15'], $ids['2026-02-28']], array_column($response->json(), 'id'));
        $this->getJson(route('dues.expenses.by-period', ['period' => '2026-01']))->assertOk()->assertJsonCount(1);
        $this->getJson(route('dues.expenses.by-period', ['period' => '2026-02', 'period_end' => '2026-01']))->assertUnprocessable()->assertJsonValidationErrors('period_end');
    }

    public function test_expense_range_total_is_confirmed_and_saved_with_both_months(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $this->grantPaidFeatures($apartment);
        foreach (['2025-12-01' => 20, '2026-01-15' => 30, '2026-02-28' => 100, '2026-03-01' => 999] as $date => $amount) {
            Expense::create(['apartment_id' => $apartment->id, 'description' => 'Test', 'amount' => $amount, 'expense_date' => $date]);
        }
        $preview = $this->post(route('dues.store'), [...$input, 'source_type' => 'expenses', 'source_period' => '2025-12', 'source_period_end' => '2026-02'])->assertOk();
        $this->assertSame(15000, $preview->viewData('summary')['amount_cents']);
        $preview->assertSee('Aralık 2025')->assertSee('Şubat 2026');
        $this->confirm($preview->viewData('token'))->assertRedirect();
        $this->assertEquals(150, Due::sum('amount'));
        $this->assertSame('2025-12-01', DueBatch::first()->source_period->toDateString());
        $this->assertSame('2026-02-01', DueBatch::first()->source_period_end->toDateString());
    }

    public function test_selected_expenses_must_belong_to_requested_range_and_apartment(): void
    {
        [$apartment, $period, $input] = $this->setupCase();
        $this->grantPaidFeatures($apartment);
        $inside = Expense::create(['apartment_id' => $apartment->id, 'description' => 'Inside', 'amount' => 75, 'expense_date' => '2026-01-15']);
        $outside = Expense::create(['apartment_id' => $apartment->id, 'description' => 'Outside', 'amount' => 999, 'expense_date' => '2026-03-01']);
        $data = [...$input, 'source_type' => 'expenses', 'source_period' => '2025-12', 'source_period_end' => '2026-02'];
        $this->post(route('dues.store'), [...$data, 'selected_expense_ids' => $inside->id.','.$outside->id])->assertSessionHasErrors('selected_expense_ids');
        $preview = $this->post(route('dues.store'), [...$data, 'selected_expense_ids' => (string) $inside->id])->assertOk();
        $this->assertSame(7500, $preview->viewData('summary')['amount_cents']);
        $this->post(route('dues.store'), [...$data, 'source_period_end' => '2025-11'])->assertSessionHasErrors('source_period_end');
        $this->assertDatabaseCount('due_batches', 0);
        $other = Apartment::create(['user_id' => auth()->id(), 'name' => 'Other', 'unit_count' => 1]);
        $foreign = Expense::create(['apartment_id' => $other->id, 'description' => 'Foreign', 'amount' => 500, 'expense_date' => '2026-01-15']);
        $this->post(route('dues.store'), [...$data, 'selected_expense_ids' => (string) $foreign->id])->assertSessionHasErrors('selected_expense_ids');
        $response = $this->getJson(route('dues.expenses.by-period', ['period' => '2025-12', 'period_end' => '2026-02']))->assertOk()->assertJsonCount(1);
        $this->assertSame($inside->id, $response->json('0.id'));
    }
}
