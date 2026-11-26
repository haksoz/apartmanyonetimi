<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ConsumptionPeriod;
use App\Models\ConsumptionSetting;
use App\Models\Unit;
use App\Models\UnitConsumption;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeterModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setupApartment(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Tüketim Test', 'unit_count' => 3]);
        $apartment->members()->attach($user->id, ['role' => $role]);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return [$apartment, $unit];
    }

    protected function read(array $values, array $initials = [], int $month = 2, string $type = 'cold_water')
    {
        $apartmentId = session(CurrentApartment::SESSION_KEY);
        $start = sprintf('2026-%02d-01', $month);
        $end = sprintf('2026-%02d-01', $month + 1);
        $setting = ConsumptionSetting::forType($apartmentId, $type);

        return $this->post(route('meters.readings.store'), ['start_date' => $start, 'end_date' => $end, 'consumption_type' => $type,
            'values' => $values, 'initials' => $initials, 'method' => $setting->method, 'measurement_unit' => $setting->measurement_unit]);
    }

    public function test_all_units_are_available_with_defaults_without_meter_setup(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        foreach (ConsumptionSetting::TYPES as $type => $label) {
            $response = $this->get(route('meters.index', ['consumption_type' => $type]));
            $response->assertOk()->assertSee($label)->assertSee('Daire 1')->assertSee('Daire 2')->assertDontSee('Sayaç Ekle');
            $this->assertSame(ConsumptionSetting::UNITS[$type], $response->viewData('setting')->measurement_unit);
        }
        $this->assertDatabaseCount('consumption_settings', 0);
        $this->get('/meters/create')->assertNotFound();
        $this->get('/meters/1/edit')->assertNotFound();
    }

    public function test_initial_index_auto_previous_zero_missing_and_duplicates(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $second = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $this->read([$unit->id => '100'])->assertSessionHasErrors('initials.'.$unit->id);
        $this->read([$unit->id => '100.1235', $second->id => ''], [$unit->id => '100.1234'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('unit_consumptions', ['unit_id' => $unit->id, 'consumption' => '0.0001']);
        $this->assertDatabaseMissing('unit_consumptions', ['unit_id' => $second->id]);
        $response = $this->get(route('meters.index'));
        $response->assertOk()->assertSee('100.1235');
        $this->read([$unit->id => '100.1235'], [$unit->id => '0'], 3)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('unit_consumptions', ['unit_id' => $unit->id, 'consumption_period_id' => ConsumptionPeriod::where('start_date', '2026-03-01')->value('id'), 'previous_value' => '100.1235', 'consumption' => 0]);
        $this->read([$unit->id => '110'])->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('unit_consumptions', 2);
    }

    public function test_heating_is_direct_by_default_and_blank_is_not_zero(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$unit->id => ''], [], 2, 'heating')->assertSessionHasErrors('values');
        $this->read([$unit->id => '0'], [], 2, 'heating')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('unit_consumptions', ['unit_id' => $unit->id, 'method' => 'direct', 'measurement_unit' => 'Pay birimi', 'consumption' => 0, 'previous_value' => null]);
    }

    public function test_shared_settings_change_without_rewriting_history_and_stale_forms_fail(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$unit->id => '120'], [$unit->id => '100'])->assertSessionHasNoErrors();
        $this->put(route('meters.settings.update'), ['consumption_type' => 'cold_water', 'method' => 'direct', 'measurement_unit' => 'litre'])->assertSessionHasNoErrors();
        $this->read([$unit->id => '40'], [], 3)->assertSessionHasNoErrors();
        $this->post(route('meters.readings.store'), ['period_id' => ConsumptionPeriod::where('start_date', '2026-03-01')->value('id'), 'consumption_type' => 'cold_water', 'method' => 'index', 'measurement_unit' => 'm³', 'values' => [$unit->id => '130']])->assertSessionHasErrors('period_id');
        $this->assertDatabaseHas('unit_consumptions', ['consumption_period_id' => ConsumptionPeriod::where('start_date', '2026-02-01')->value('id'), 'measurement_unit' => 'm³', 'method' => 'index', 'consumption' => 20]);
        $this->assertDatabaseHas('unit_consumptions', ['consumption_period_id' => ConsumptionPeriod::where('start_date', '2026-03-01')->value('id'), 'measurement_unit' => 'litre', 'method' => 'direct', 'consumption' => 40]);
        $response = $this->get(route('meters.history'));
        $response->assertOk();
        $this->assertCount(2, $response->viewData('periodSummaries'));
    }

    public function test_previous_periods_and_gaps_in_history_cannot_be_filled_from_entry_screen(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$unit->id => '150'], [$unit->id => '100'], 3)->assertSessionHasNoErrors();
        $this->read([$unit->id => '120'], [$unit->id => '100'], 2)->assertSessionHasErrors('start_date');
        $this->read([$unit->id => '121'], [$unit->id => '100'], 1)->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('consumption_periods', 1);
        $this->assertDatabaseCount('unit_consumptions', 1);
        $this->read([$unit->id => '160.00001'], [], 4)->assertSessionHasErrors();
        $this->read([$unit->id => '-1'], [], 4)->assertSessionHasErrors();
        $this->read([$unit->id => '160'], [], 4)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('unit_consumptions', ['consumption' => 10]);
    }

    public function test_apartment_isolation_history_filters_and_member_authorization(): void
    {
        [$other, $foreign] = $this->setupApartment();
        $this->read([$foreign->id => '4'], [], 2, 'heating')->assertSessionHasNoErrors();
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$foreign->id => '10'], [], 2, 'heating')->assertSessionHasErrors('values.'.$foreign->id);
        $this->read([$unit->id => '5'], [], 2, 'heating')->assertSessionHasNoErrors();
        $this->get(route('meters.history'))->assertOk();
        $response = $this->get(route('meters.history', ['consumption_type' => 'heating']));
        $response->assertOk();
        $this->assertCount(1, $response->viewData('periodSummaries'));
        $this->assertEquals(5, $response->viewData('periodSummaries')->first()->total);
        $this->get(route('meters.history', ['consumption_type' => 'electricity']))->assertOk()->assertSee('Seçili filtrelerde ölçüm bulunamadı.');
        $this->setupApartment('member');
        $this->get(route('meters.index'))->assertForbidden();
        $this->get(route('meters.history'))->assertForbidden();
        $this->read([1 => '0'])->assertForbidden();
        $this->put(route('meters.settings.update'))->assertForbidden();
    }

    public function test_database_prevents_duplicate_unit_type_period_records(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$unit->id => '10'], [], 2, 'heating')->assertSessionHasNoErrors();
        $record = UnitConsumption::first()->replicate();
        $this->expectException(UniqueConstraintViolationException::class);
        $record->save();
    }

    public function test_consumption_belongs_to_unit_and_no_financial_records_are_created(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $before = [];
        foreach (['dues', 'expenses', 'payments'] as $table) {
            $before[$table] = DB::table($table)->count();
        }
        $this->read([$unit->id => '3.125'], [], 2, 'heating')->assertSessionHasNoErrors();
        $unit->update(['resident_name' => 'Yeni Sakin', 'owner_account_id' => null, 'occupant_account_id' => null]);
        $this->assertSame($unit->id, UnitConsumption::first()->unit_id);
        $this->get(route('meters.history.detail', UnitConsumption::first()->consumption_period_id))->assertOk()->assertSee('3.1250');
        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
    }

    public function test_single_save_creates_period_and_blocks_reusing_it_even_for_missing_units(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $second = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $data = ['consumption_type' => 'cold_water', 'start_date' => '2026-01-18', 'end_date' => '2026-02-23', 'method' => 'index', 'measurement_unit' => 'm³'];
        $this->get(route('meters.index'))->assertOk()->assertSee('Daire 1')->assertSee('Daire 2')->assertDontSee('Dönem Oluştur');
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$unit->id => '135'], 'initials' => [$unit->id => '120']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('consumption_periods', 1);
        $this->assertDatabaseHas('unit_consumptions', ['unit_id' => $unit->id, 'consumption' => 15]);
        $period = ConsumptionPeriod::first();
        $this->get(route('meters.index', ['period_id' => $period->id]))->assertRedirect(route('meters.history.detail', $period->id));
        $this->get(route('meters.index'))->assertOk()->assertDontSee('Dönemi Göster')->assertDontSee('name="period_id"', false)->assertSee('name="start_date"', false)->assertSee('Daire 1');
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$second->id => '0'], 'initials' => [$second->id => '0']])->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('consumption_periods', 1);
        $this->assertDatabaseCount('unit_consumptions', 1);
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$unit->id => '140']])->assertSessionHasErrors('start_date');
        $this->get(route('meters.index'))->assertViewHas('suggestedStart', '2026-02-23')->assertSee('135.0000');
    }

    public function test_failed_or_empty_submission_does_not_create_period(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $data = ['consumption_type' => 'cold_water', 'start_date' => '2026-01-18', 'end_date' => '2026-02-23', 'method' => 'index', 'measurement_unit' => 'm³'];
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$unit->id => '']])->assertSessionHasErrors();
        $this->assertDatabaseCount('consumption_periods', 0);
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$unit->id => '10'], 'initials' => [$unit->id => '20']])->assertSessionHasErrors();
        $this->assertDatabaseCount('consumption_periods', 0);
        $this->assertDatabaseCount('unit_consumptions', 0);
        $this->post(route('meters.readings.store'), [...$data, 'values' => [$unit->id => '20'], 'initials' => [$unit->id => '10']])->assertSessionHasNoErrors();
        $this->post(route('meters.readings.store'), [...$data, 'start_date' => '2026-02-01', 'end_date' => '2026-03-10', 'values' => [$unit->id => '30']])->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('consumption_periods', 1);
    }

    public function test_date_validation_shared_boundary_and_stale_settings(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $data = ['consumption_type' => 'heating', 'method' => 'direct', 'measurement_unit' => 'Pay birimi', 'values' => [$unit->id => '2']];
        foreach ([['2026-02-23', '2026-02-23'], ['2026-03-01', '2026-02-01'], ['invalid', '2026-02-01']] as [$start, $end]) {
            $this->post(route('meters.readings.store'), [...$data, 'start_date' => $start, 'end_date' => $end])->assertSessionHasErrors();
        }
        $this->post(route('meters.readings.store'), [...$data, 'start_date' => '2026-01-18', 'end_date' => '2026-02-23'])->assertSessionHasNoErrors();
        $this->post(route('meters.readings.store'), [...$data, 'start_date' => '2026-02-23', 'end_date' => '2026-03-27'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('consumption_periods', 2);
        $this->put(route('meters.settings.update'), ['consumption_type' => 'heating', 'method' => 'direct', 'measurement_unit' => 'kWh'])->assertSessionHasNoErrors();
        $this->post(route('meters.readings.store'), [...$data, 'start_date' => '2026-03-27', 'end_date' => '2026-04-30'])->assertSessionHasErrors('settings');
        $this->assertDatabaseCount('consumption_periods', 2);
    }

    public function test_period_from_another_apartment_or_type_cannot_be_used(): void
    {
        [$other, $foreign] = $this->setupApartment();
        $this->read([$foreign->id => '4'], [], 2, 'heating')->assertSessionHasNoErrors();
        $period = ConsumptionPeriod::first();
        [$apartment, $unit] = $this->setupApartment();
        $this->get(route('meters.index', ['consumption_type' => 'heating', 'period_id' => $period->id]))->assertNotFound();
        $this->post(route('meters.readings.store'), ['consumption_type' => 'heating', 'period_id' => $period->id, 'method' => 'direct', 'measurement_unit' => 'Pay birimi', 'values' => [$unit->id => '5']])->assertSessionHasErrors('period_id');
        $this->assertDatabaseCount('unit_consumptions', 1);
    }

    public function test_history_groups_units_into_one_period_row_and_opens_details(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $second = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '2']);
        $this->read([$unit->id => '3', $second->id => '7'], [], 2, 'heating')->assertSessionHasNoErrors();
        $response = $this->get(route('meters.history'));
        $response->assertOk();
        $summaries = $response->viewData('periodSummaries');
        $this->assertCount(1, $summaries);
        $this->assertEquals(2, $summaries->first()->reading_count);
        $this->assertEquals(10, $summaries->first()->total);
        $periodId = $summaries->first()->id;
        $response->assertSee(route('meters.history.detail', $periodId), false);
        $detail = $this->get(route('meters.history.detail', $periodId));
        $detail->assertOk()->assertSee('3.0000')->assertSee('7.0000');
        $this->assertCount(2, $detail->viewData('readings'));
        $filtered = $this->get(route('meters.history', ['unit_id' => $unit->id]));
        $filtered->assertOk();
        $this->assertEquals(10, $filtered->viewData('periodSummaries')->first()->total);
        $this->setupApartment();
        $this->get(route('meters.history.detail', $periodId))->assertNotFound();
        $this->setupApartment('member');
        $this->get(route('meters.history.detail', $periodId))->assertForbidden();
    }

    public function test_history_only_filters_by_consumption_type(): void
    {
        [$apartment, $unit] = $this->setupApartment();
        $this->read([$unit->id => '5'], [], 2, 'heating')->assertSessionHasNoErrors();
        $this->read([$unit->id => '120'], [$unit->id => '100'], 3)->assertSessionHasNoErrors();
        $all = $this->get(route('meters.history'));
        $all->assertOk()->assertDontSee('Apartman Toplam Tüketimi')->assertSee('name="consumption_type"', false)->assertDontSee('name="year"', false)->assertDontSee('name="month"', false)->assertDontSee('name="unit_id"', false)->assertDontSee('name="period_id"', false);
        $this->assertCount(2, $all->viewData('periodSummaries'));
        $filtered = $this->get(route('meters.history', ['consumption_type' => 'heating', 'year' => 1900, 'month' => 12, 'unit_id' => 999999, 'period_id' => 999999]));
        $filtered->assertOk();
        $this->assertCount(1, $filtered->viewData('periodSummaries'));
        $this->assertEquals(5, $filtered->viewData('periodSummaries')->first()->total);
        $this->get(route('meters.history', ['consumption_type' => 'invalid']))->assertSessionHasErrors('consumption_type');
    }
}
