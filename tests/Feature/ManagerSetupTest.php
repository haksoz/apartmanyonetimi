<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerSetupTest extends TestCase
{
    use RefreshDatabase;

    private function setupApartment(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'account_opening_date' => '2026-01-01',
            'setup_manager_completed_at' => null,
            'setup_completed_at' => null,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '02']);
        $owner = Account::create([
            'apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER, 'name' => '02. Daire Kat Maliki',
            'account_opening_date' => '2026-01-01', 'is_active' => true,
        ]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        return [$user, $apartment, $unit, $owner];
    }

    public function test_new_apartment_creation_enters_the_manager_step(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('subscriber.apartments.store'), [
            'name' => 'Yeni Apartman', 'address' => 'Adres', 'unit_count' => 4,
            'account_opening_date' => '2026-01-01', 'accept_resident_data' => 1, 'accept_privacy' => 1,
        ]);
        $apartment = Apartment::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('apartments.wizard.manager', $apartment));
        $this->assertSame('manager', $apartment->nextSetupStep());
        $this->actingAs($user)->get(route('apartments.wizard.manager', $apartment))
            ->assertOk()->assertSee('Yönetici ve Daire Bilgileri')->assertSee('min="2026-01-01"', false)
            ->assertSee('Kat malikiyim')->assertSee('Kiracıyım')->assertSee('Yalnızca yöneticiyim')
            ->assertDontSee('burada oturuyorum')->assertDontSee('burada oturmuyorum');
    }

    public function test_tenant_is_linked_once_without_an_invitation(): void
    {
        [$user, $apartment, $unit, $owner] = $this->setupApartment();
        $data = ['manager_type' => 'tenant', 'unit_id' => $unit->id, 'started_at' => '2026-02-01'];
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), $data)
            ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), $data)->assertRedirect();
        $tenant = $apartment->accounts()->where('type', Account::TYPE_TENANT)->sole();
        $this->assertSame($user->id, $tenant->user_id);
        $this->assertSame($user->email, $tenant->email);
        $this->assertSame($tenant->id, $unit->fresh()->occupant_account_id);
        $this->assertNull($owner->fresh()->user_id);
        $this->assertDatabaseCount('tenant_assignments', 1);
        $this->assertSame('2026-02-01', $tenant->tenantAssignments()->sole()->move_in_date->toDateString());
        $this->assertDatabaseCount('user_invitations', 0);
        $this->assertSame('owner', $apartment->members()->whereKey($user->id)->first()->pivot->role);
    }

    public function test_owner_uses_existing_account_when_ownership_starts_at_opening(): void
    {
        foreach (['owner'] as $type) {
            [$user, $apartment, $unit, $owner] = $this->setupApartment();
            $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), [
                'manager_type' => $type, 'unit_id' => $unit->id, 'started_at' => '2026-01-01',
            ])->assertRedirect(route('apartments.wizard.cash-box', $apartment));
            $this->assertSame($user->id, $owner->fresh()->user_id);
            $this->assertSame($user->email, $owner->fresh()->email);
            $this->assertSame($type === 'owner' ? $owner->id : null, $unit->fresh()->occupant_account_id);
            $this->assertSame('2026-01-01', $unit->ownerHistories()->sole()->start_date->toDateString());
            $this->assertSame(1, $apartment->accounts()->count());
        }
    }

    public function test_external_manager_does_not_link_an_account(): void
    {
        [$user, $apartment, $unit, $owner] = $this->setupApartment();
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), ['manager_type' => 'external'])
            ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
        $this->assertNotNull($apartment->fresh()->setup_manager_completed_at);
        $this->assertNull($owner->fresh()->user_id);
    }

    public function test_later_ownership_preserves_the_previous_period_and_opens_a_new_account(): void
    {
        foreach (['owner'] as $type) {
            [$user, $apartment, $unit, $previous] = $this->setupApartment();
            $data = ['manager_type' => $type, 'unit_id' => $unit->id, 'started_at' => '2026-03-01'];
            $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), $data)
                ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
            $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), $data)
                ->assertRedirect();

            $previous->refresh();
            $this->assertFalse((bool) $previous->is_active);
            $this->assertSame('2026-01-01', $previous->account_opening_date->toDateString());
            $this->assertSame('2026-02-28', $previous->account_end_date->toDateString());
            $this->assertNull($previous->user_id);
            $this->assertSame('02. Daire Kat Maliki', $previous->name);

            $current = $unit->fresh()->ownerAccount;
            $this->assertNotSame($previous->id, $current->id);
            $this->assertSame($user->id, $current->user_id);
            $this->assertSame($user->email, $current->email);
            $this->assertSame('2026-03-01', $current->account_opening_date->toDateString());
            $this->assertNull($current->account_end_date);
            $this->assertTrue((bool) $current->is_active);
            $this->assertSame($type === 'owner' ? $current->id : null, $unit->fresh()->occupant_account_id);
            $history = $unit->ownerHistories()->where('account_id', $previous->id)->sole();
            $this->assertSame('2026-01-01', $history->start_date->toDateString());
            $this->assertSame('2026-02-28', $history->end_date->toDateString());
            $this->assertSame('2026-03-01', $unit->ownerHistories()->where('account_id', $current->id)->sole()->start_date->toDateString());
            $this->assertSame(2, $apartment->accounts()->count());
            $this->assertSame(2, $unit->ownerHistories()->count());
        }
    }

    public function test_dates_before_opening_and_foreign_units_are_rejected(): void
    {
        [$user, $apartment, $unit] = $this->setupApartment();
        foreach (['tenant', 'owner'] as $type) {
            $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), [
                'manager_type' => $type, 'unit_id' => $unit->id, 'started_at' => '2025-12-31',
            ])->assertSessionHasErrors('started_at');
        }
        [, , $otherUnit] = $this->setupApartment();
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), [
            'manager_type' => 'tenant', 'unit_id' => $otherUnit->id, 'started_at' => '2026-01-01',
        ])->assertSessionHasErrors('unit_id');
        $this->assertNull($apartment->fresh()->setup_manager_completed_at);
        $this->assertDatabaseCount('tenant_assignments', 0);
    }

    public function test_another_users_owner_account_is_not_overwritten(): void
    {
        [$user, $apartment, $unit, $owner] = $this->setupApartment();
        $other = User::factory()->create();
        $owner->update(['user_id' => $other->id, 'email' => $other->email]);
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), [
            'manager_type' => 'owner', 'unit_id' => $unit->id, 'started_at' => '2026-01-01',
        ])->assertSessionHasErrors('unit_id');
        $this->assertSame($other->id, $owner->fresh()->user_id);
        $this->assertNull($apartment->fresh()->setup_manager_completed_at);
    }

    public function test_manager_step_cannot_be_bypassed_or_accessed_by_another_user(): void
    {
        [$user, $apartment] = $this->setupApartment();
        foreach (['cash-box', 'units', 'categories'] as $step) {
            $this->actingAs($user)->get(route('apartments.wizard.'.$step, $apartment))
                ->assertRedirect(route('apartments.wizard.manager', $apartment));
        }
        $this->actingAs($user)->post(route('apartments.wizard.finish', $apartment))
            ->assertRedirect(route('apartments.wizard.manager', $apartment));
        $this->actingAs($user)->post(route('apartments.wizard.cash-box.store', $apartment), ['name' => 'Erken Kasa'])
            ->assertRedirect(route('apartments.wizard.manager', $apartment));
        $this->assertSame(0, $apartment->cashBoxes()->count());
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('apartments.wizard.manager.store', $apartment), ['manager_type' => 'external'])
            ->assertForbidden();
    }

    public function test_tenant_setup_does_not_replace_an_existing_occupant(): void
    {
        [$user, $apartment, $unit] = $this->setupApartment();
        $tenant = $apartment->accounts()->create(['unit_id' => $unit->id, 'type' => Account::TYPE_TENANT, 'name' => 'Mevcut Kiracı']);
        $unit->update(['occupant_account_id' => $tenant->id]);
        $this->actingAs($user)->post(route('apartments.wizard.manager.store', $apartment), [
            'manager_type' => 'tenant', 'unit_id' => $unit->id, 'started_at' => '2026-01-01',
        ])->assertSessionHasErrors('unit_id');
        $this->assertSame($tenant->id, $unit->fresh()->occupant_account_id);
        $this->assertSame(2, $apartment->accounts()->count());
        $this->assertNull($apartment->fresh()->setup_manager_completed_at);
    }
}
