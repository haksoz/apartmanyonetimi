<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Category, Due, Payment, TenantAssignment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueTargetAudienceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Rule test apartment', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'owner', 'name' => 'Owner', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Rule test dues',
            'type' => Category::TYPE_INCOME, 'is_active' => true, 'allow_aidat' => true]);
        $apartment->update(['default_aidat_category_id' => $category->id]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);

        return [$apartment, $unit, $owner, $category];
    }

    public function test_batch_rules_include_tenant_priority_owner_fallback_and_owner_only(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $tenant->id, 'move_in_date' => '2026-06-01']);
        foreach ([['2026-06', 'tenant_priority', $tenant->id], ['2026-07', 'owner_only', $owner->id],
            ['2026-02', 'tenant_priority', $owner->id]] as [$period, $rule, $accountId]) {
            $this->post(route('dues.store'), [
                'source_type' => 'fixed', 'distribution_type' => 'equal', 'fixed_amount' => 100,
                'target_audience' => $rule, 'due_type' => 'aidat', 'category_id' => $category->id,
                'period' => $period, 'due_date' => $period.'-10',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $due = Due::where('period', $period)->sole();
            $this->assertSame($rule, $due->target_audience);
            $this->assertSame($accountId, $due->account_id);
            $this->get(route('dues.show', $due))->assertOk()->assertSee('Oluşturma Kuralı')
                ->assertSee($rule === 'owner_only' ? 'Sadece Kat Maliki' : 'Kiracı Öncelikli');
        }
    }

    public function test_individual_rule_is_unknown_and_remains_unknown_after_editing(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $this->post(route('dues.store'), [
            'source_type' => 'individual', 'distribution_type' => 'individual', 'individual_amount' => 100,
            'target_audience' => 'tenant_priority', 'account_id' => $owner->id,
            'due_type' => 'aidat', 'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $due = Due::sole();
        $this->assertNull($due->target_audience);
        $this->assertSame('tenant_priority', $due->batch->target_audience);
        $this->editDue($due, $owner, $category);
        $this->assertNull($due->fresh()->target_audience);
        $this->get(route('dues.show', $due))->assertOk()->assertSee('Belirlenmemiş');
    }

    private function editDue(Due $due, Account $owner, Category $category): void
    {
        $this->patch(route('dues.update', $due), [
            'account_id' => $owner->id, 'category_id' => $category->id, 'due_type' => 'aidat',
            'period' => $due->period, 'due_date' => '2026-02-10', 'amount' => 120,
            'target_audience' => 'owner_only',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(120, $due->fresh()->amount);
    }

    public function test_rule_survives_edit_transfer_and_partial_and_full_payment(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $due = Due::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $owner->id, 'category_id' => $category->id, 'due_type' => 'aidat',
            'period' => '2026-02', 'due_date' => '2026-02-10', 'amount' => 100,
            'remaining_amount' => 100, 'status' => 'unpaid', 'target_audience' => 'tenant_priority']);
        $this->editDue($due, $owner, $category);
        $this->assertSame('tenant_priority', $due->fresh()->target_audience);
        $this->post(route('dues.transfer', $due), ['target_account_id' => $tenant->id,
            'target_audience' => 'owner_only'])->assertSessionHasNoErrors()->assertRedirect();
        $due->refresh();
        $this->assertSame($tenant->id, $due->account_id);
        $this->assertSame('tenant_priority', $due->target_audience);
        foreach ([20, 100] as $amount) {
            $payment = Payment::create(['apartment_id' => $apartment->id, 'account_id' => $tenant->id,
                'amount' => $amount, 'unallocated_amount' => $amount, 'payment_date' => '2026-03-01']);
            $payment->allocateToDue($due, $amount);
            $this->assertSame('tenant_priority', $due->fresh()->target_audience);
        }
        $this->assertEquals(0, $due->fresh()->remaining_amount);
        $this->assertEquals(120, $due->allocations()->sum('amount'));
    }

    public function test_migration_preserves_existing_rows_without_assigning_a_rule(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $migration = require database_path('migrations/2026_10_27_000001_add_target_audience_to_dues.php');
        $migration->down();
        $id = \Illuminate\Support\Facades\DB::table('dues')->insertGetId([
            'apartment_id' => $apartment->id, 'account_id' => $owner->id, 'unit_id' => $unit->id,
            'amount' => 100, 'remaining_amount' => 75, 'due_date' => '2026-02-10', 'status' => 'partial',
        ]);
        $migration->up();
        $due = Due::findOrFail($id);
        $this->assertNull($due->target_audience);
        $this->assertEquals(75, $due->remaining_amount);
        $this->assertSame($owner->id, $due->account_id);
    }

    public function test_invalid_rule_is_rejected_when_creating_a_due(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->expectException(\InvalidArgumentException::class);
        Due::create(['apartment_id' => $apartment->id, 'account_id' => $owner->id,
            'unit_id' => $unit->id, 'amount' => 100, 'remaining_amount' => 100,
            'due_date' => '2026-02-10', 'status' => 'unpaid', 'target_audience' => 'invalid']);
    }

    public function test_existing_rule_cannot_be_overwritten_on_the_model(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $due = Due::create(['apartment_id' => $apartment->id, 'account_id' => $owner->id,
            'unit_id' => $unit->id, 'amount' => 100, 'remaining_amount' => 100,
            'due_date' => '2026-02-10', 'status' => 'unpaid']);
        $this->assertNull($due->target_audience);
        $this->expectException(\LogicException::class);
        $due->update(['target_audience' => 'owner_only']);
    }
}
