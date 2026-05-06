<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_index_lists_selected_apartment_accounts(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        Account::create([
            'apartment_id' => $firstApartment->id,
            'type' => 'resident',
            'name' => 'Birinci Apartman Hesabı',
        ]);
        $selectedAccount = Account::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'resident',
            'name' => 'İkinci Apartman Hesabı',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->get(route('accounts.index'))
            ->assertStatus(200)
            ->assertSee($selectedAccount->name)
            ->assertDontSee('Birinci Apartman Hesabı');
    }

    public function test_account_detail_opens_for_selected_apartment_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => 'resident',
            'name' => '1. Daire',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $account))
            ->assertStatus(200)
            ->assertSee('1. Daire')
            ->assertSee('1 no.lu daire');
    }

    public function test_user_can_create_supplier_account_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_SUPPLIER,
                'unit_id' => $unit->id,
                'name' => 'Elektrik Firması',
                'phone' => '555 111 22 33',
                'email' => 'elektrik@example.com',
                'balance' => 0,
                'account_opening_date' => '2026-05-04',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.index'));

        $account = Account::where('name', 'Elektrik Firması')->firstOrFail();

        $this->assertDatabaseHas('accounts', [
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Elektrik Firması',
            'unit_id' => null,
        ]);
        $this->assertSame('2026-05-04', $account->account_opening_date->format('Y-m-d'));
    }

    public function test_user_can_update_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Eski Ünvan',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('accounts.update', $account), [
                'type' => Account::TYPE_SUPPLIER,
                'name' => 'Yeni Ünvan',
                'balance' => 100,
                'account_opening_date' => '2026-05-04',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.show', $account));

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Yeni Ünvan',
            'balance' => 100,
        ]);
    }

    public function test_user_can_soft_delete_account(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Silinecek Tedarikçi',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertSoftDeleted('accounts', [
            'id' => $account->id,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.index'))
            ->assertStatus(200)
            ->assertDontSee('Silinecek Tedarikçi');
    }

    public function test_user_can_create_tenant_account_with_move_in_date(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_TENANT,
                'unit_id' => $unit->id,
                'name' => 'Kiracı Ali',
                'move_in_date' => '2026-05-01',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.index'));

        $tenant = Account::where('name', 'Kiracı Ali')->firstOrFail();

        $assignment = TenantAssignment::where('account_id', $tenant->id)->firstOrFail();
        $this->assertSame($apartment->id, $assignment->apartment_id);
        $this->assertSame($unit->id, $assignment->unit_id);
        $this->assertSame('2026-05-01', $assignment->move_in_date->format('Y-m-d'));
        $this->assertNull($assignment->move_out_date);
        $this->assertSame($tenant->id, $unit->fresh()->occupant_account_id);
    }

    public function test_user_cannot_create_second_active_tenant_for_same_unit(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        $tenant = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT,
            'name' => 'Mevcut Kiracı',
        ]);
        TenantAssignment::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'move_in_date' => '2026-05-01',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_TENANT,
                'unit_id' => $unit->id,
                'name' => 'Yeni Kiracı',
                'move_in_date' => '2026-06-01',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_user_can_set_tenant_move_out_date(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $owner = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
        ]);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'owner_account_id' => $owner->id,
            'unit_no' => '1',
        ]);
        $tenant = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT,
            'name' => 'Kiracı Ali',
        ]);
        TenantAssignment::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'move_in_date' => '2026-05-01',
        ]);
        $unit->update(['occupant_account_id' => $tenant->id]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('accounts.update', $tenant), [
                'type' => Account::TYPE_TENANT,
                'unit_id' => $unit->id,
                'name' => 'Kiracı Ali',
                'move_in_date' => '2026-05-01',
                'move_out_date' => '2026-06-01',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.show', $tenant));

        $this->assertSame('2026-06-01', TenantAssignment::where('account_id', $tenant->id)->firstOrFail()->move_out_date->format('Y-m-d'));
        $this->assertSame($owner->id, $unit->fresh()->occupant_account_id);
    }

    public function test_user_cannot_create_second_owner_for_same_unit(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Mevcut Malik',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_OWNER,
                'unit_id' => $unit->id,
                'name' => 'Yeni Malik',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_user_can_update_existing_owner_for_same_unit(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        $owner = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Eski Malik',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('accounts.update', $owner), [
                'type' => Account::TYPE_OWNER,
                'unit_id' => $unit->id,
                'name' => 'Yeni Malik',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.show', $owner));

        $this->assertDatabaseHas('accounts', [
            'id' => $owner->id,
            'name' => 'Yeni Malik',
        ]);
        $this->assertSame($owner->id, $unit->fresh()->owner_account_id);
    }
}
