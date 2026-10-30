<?php

namespace Tests\Feature;

use App\Enums\DueType;
use App\Http\Controllers\DuePlanController;
use App\Models\{Account, Apartment, Category, Due, DuePlan, Payment, TenantAssignment, Unit, User};
use App\Support\{CurrentApartment, DueTargeting};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DueTypeTargetingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Hedefleme', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'owner', 'name' => 'Malik', 'is_active' => true]);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'tenant', 'name' => 'Kiracı', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $tenant->id]);
        $assignment = TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'account_id' => $tenant->id, 'move_in_date' => '2026-01-01']);
        Category::createDefaultsFor($apartment->id);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Ortak İş', 'is_active' => true, 'allow_aidat' => true, 'allow_malik_payi' => true]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        return [$apartment, $unit, $owner, $tenant, $assignment, $category];
    }

    private function payload(Account $account, Category $category, string $type = 'aidat'): array
    {
        return ['source_type' => 'individual', 'distribution_type' => 'individual', 'account_id' => $account->id,
            'due_type' => $type, 'category_id' => $category->id, 'individual_amount' => 100,
            'period' => '2026-06', 'due_date' => '2026-06-20'];
    }

    private function due(Account $account, Category $category, string $type = 'aidat'): Due
    {
        return Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id, 'unit_id' => $account->unit_id,
            'due_type' => $type, 'category_id' => $category->id, 'period' => '2026-06', 'due_date' => '2026-06-20',
            'amount' => 100, 'remaining_amount' => 100, 'status' => 'unpaid']);
    }

    public function test_individual_aidat_blocks_owner_and_accepts_the_period_tenant(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $this->postJson(route('dues.store'), $this->payload($owner, $category))->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertDatabaseCount('due_batches', 0);
        $this->assertDatabaseCount('account_transactions', 0);
        $this->post(route('dues.store'), $this->payload($tenant, $category))->assertSessionHasNoErrors();
        $due = Due::sole();
        $this->assertSame($tenant->id, $due->account_id);
        $this->assertSame('aidat', $due->due_type->value);
        $this->assertEquals(100, $due->remaining_amount);
    }

    public function test_owner_fallback_uses_period_dates_and_does_not_charge_a_departed_tenant(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $assignment->update(['move_out_date' => '2026-05-31']);
        $this->postJson(route('dues.store'), $this->payload($tenant, $category))->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->post(route('dues.store'), $this->payload($owner, $category))->assertSessionHasNoErrors();
        $this->assertSame($owner->id, Due::sole()->account_id);
    }

    public function test_malik_payi_only_accepts_owner_even_if_a_tenant_exists(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $this->postJson(route('dues.store'), $this->payload($tenant, $category, 'malik_payi'))->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->post(route('dues.store'), $this->payload($owner, $category, 'malik_payi'))->assertSessionHasNoErrors();
        $this->assertSame($owner->id, Due::sole()->account_id);
        $this->assertSame('malik_payi', Due::sole()->due_type->value);
    }

    public function test_interface_eligibility_uses_the_same_server_rule_and_period(): void
    {
        [$apartment, $unit, $owner, $tenant] = $this->fixture();
        $response = $this->getJson(route('dues.account-eligibility', ['period' => '2026-06']))->assertOk();
        $this->assertNotEmpty($response->json("accounts.{$owner->id}.aidat"));
        $this->assertNull($response->json("accounts.{$tenant->id}.aidat"));
        $this->assertNotEmpty($response->json("accounts.{$tenant->id}.malik_payi"));
        $this->assertNull($response->json("accounts.{$owner->id}.malik_payi"));
        $this->get(route('dues.create', ['account_id' => $owner->id]))->assertOk()
            ->assertSee('Hesap uygunluğu kontrol edilemedi.')
            ->assertSee('loadEligibility', false)
            ->assertDontSee('name="target_audience"', false);
        $this->getJson(route('dues.account-eligibility', ['period' => '2025-12']))->assertOk()
            ->assertJsonPath("accounts.{$owner->id}.aidat", null);
    }

    public function test_saved_type_cannot_change_and_payments_and_balances_are_untouched(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $due = $this->due($tenant, $category);
        $payment = Payment::create(['apartment_id' => $apartment->id, 'account_id' => $tenant->id, 'amount' => 25, 'unallocated_amount' => 25, 'payment_date' => '2026-06-20']);
        $payment->allocateToDue($due, 25);
        $tables = ['dues', 'payments', 'payment_allocations', 'account_transactions'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $this->patchJson(route('dues.update', $due), ['due_type' => 'malik_payi', 'category_id' => $category->id,
            'period' => '2026-06', 'due_date' => '2026-06-20', 'amount' => 200])->assertUnprocessable()->assertJsonValidationErrors('due_type');
        $this->assertSame($before, $snapshot());
        $this->get(route('dues.edit', $due))->assertOk()->assertDontSee('<select id="due_type"', false)->assertSee('Kaydedilmiş borcun türü değiştirilemez.');
        try {
            $due->update(['due_type' => 'malik_payi']);
            $this->fail('Model tür değişikliğini engellemeli.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('due_type', $exception->errors());
        }
        $this->assertSame(DueType::Aidat, $due->fresh()->due_type);
    }

    public function test_transfers_cannot_bypass_aidat_or_malik_account_rules(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $aidat = $this->due($tenant, $category);
        $malik = $this->due($owner, $category, 'malik_payi');
        $this->postJson(route('dues.transfer', $aidat), ['target_account_id' => $owner->id])->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->postJson(route('dues.transfer', $malik), ['target_account_id' => $tenant->id])->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertSame($tenant->id, $aidat->fresh()->account_id);
        $this->assertSame($owner->id, $malik->fresh()->account_id);
        $this->assertSame(DueType::MalikPayi, $malik->fresh()->due_type);
        $assignment->update(['move_out_date' => '2026-05-31']);
        $this->post(route('dues.transfer', $aidat), ['target_account_id' => $owner->id])->assertSessionHasNoErrors();
        $this->assertSame($owner->id, $aidat->fresh()->account_id);
        $this->assertSame(DueType::Aidat, $aidat->fresh()->due_type);
    }

    public function test_ambiguous_tenancies_fail_safely_instead_of_picking_an_arbitrary_tenant(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $second = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'tenant', 'name' => 'İkinci', 'is_active' => true]);
        TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'account_id' => $second->id, 'move_in_date' => '2026-06-01']);
        $this->postJson(route('dues.store'), $this->payload($tenant, $category))->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertDatabaseCount('dues', 0);
        $this->expectException(ValidationException::class);
        app(DueTargeting::class)->accountFor($unit, Carbon::parse('2026-06-01'), DueType::Aidat);
    }

    public function test_automatic_aidat_generation_uses_the_same_tenant_and_owner_fallback(): void
    {
        [$apartment, $unit, $owner, $tenant, $assignment, $category] = $this->fixture();
        $plan = DuePlan::create(['apartment_id' => $apartment->id, 'due_type' => 'aidat', 'category_id' => $apartment->fresh()->default_aidat_category_id,
            'name' => 'Aidat', 'plan_year' => 2026, 'amount_type' => 'monthly', 'monthly_amount' => 100,
            'distribution_type' => 'equal', 'due_day' => 10, 'generate_day' => 1, 'is_active' => true]);
        app(DuePlanController::class)->createDuesForPeriod($plan, '2026-06');
        $this->assertSame($tenant->id, Due::where('period', '2026-06')->sole()->account_id);
        $assignment->update(['move_out_date' => '2026-06-30']);
        app(DuePlanController::class)->createDuesForPeriod($plan, '2026-07');
        $this->assertSame($owner->id, Due::where('period', '2026-07')->sole()->account_id);
        $this->assertSame(['aidat'], Due::distinct()->pluck('due_type')->map(fn ($type) => $type->value)->all());
    }
}
