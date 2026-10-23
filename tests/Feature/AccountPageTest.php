<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Due;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_detail_lists_open_payments_newest_to_oldest(): void
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
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
        ]);

        $oldPayment = \App\Models\Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 100,
            'unallocated_amount' => 100,
            'payment_date' => '2023-01-15',
        ]);
        $newPayment = \App\Models\Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 200,
            'unallocated_amount' => 200,
            'payment_date' => '2025-08-20',
        ]);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $account));

        $response->assertStatus(200);
        $this->assertStringContainsString('20.08.2025', $response->getContent());
        $this->assertStringContainsString('15.01.2023', $response->getContent());

        $newPos = strpos($response->getContent(), '20.08.2025');
        $oldPos = strpos($response->getContent(), '15.01.2023');
        $this->assertLessThan($oldPos, $newPos, 'Newest payment should appear before oldest payment');
    }

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
            ->assertSee('Daire 01')
            ->assertSee('1. Daire (Resident)', false)
            ->assertSee('text-base font-semibold text-slate-800', false)
            ->assertSee('Resident')
            ->assertDontSee('Detaylar')
            ->assertDontSee('Hesap Türü')
            ->assertSee(route('accounts.index'), false);
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
        $this->assertDatabaseMissing('account_transactions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_opening_balance_is_posted_as_a_single_account_transaction(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_SUPPLIER,
                'name' => 'Doğalgaz',
                'balance' => '250.50',
                'opening_side' => 'credit',
                'account_opening_date' => '2026-10-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.index'));

        $account = Account::where('name', 'Doğalgaz')->firstOrFail();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'balance' => 0,
        ]);
        $transaction = AccountTransaction::where('account_id', $account->id)->firstOrFail();
        $this->assertSame('credit', $transaction->type);
        $this->assertSame('Açılış bakiyesi', $transaction->description);
        $this->assertSame('250.50', $transaction->amount);
        $this->assertSame('2026-10-01', $transaction->transaction_date->format('Y-m-d'));
        $this->assertNull($transaction->transactionable_type);
        $this->assertSame(1, AccountTransaction::where('account_id', $account->id)->count());
        $this->assertDatabaseMissing('cash_transactions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_opening_balance_requires_a_side(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_SUPPLIER,
                'name' => 'Su',
                'balance' => '100',
                'account_opening_date' => '2026-10-01',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('opening_side');
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
            'balance' => 0,
        ]);
        $this->assertDatabaseMissing('account_transactions', [
            'account_id' => $account->id,
        ]);
    }

    public function test_updating_opening_date_moves_opening_balance_transaction(): void
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
            'name' => 'Enerji',
            'account_opening_date' => '2026-01-01',
        ]);
        $opening = AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Açılış bakiyesi',
            'amount' => 250,
            'transaction_date' => '2026-01-01',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('accounts.update', $account), [
                'type' => Account::TYPE_SUPPLIER,
                'name' => 'Enerji',
                'account_opening_date' => '2026-03-15',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.show', $account));

        $this->assertDatabaseHas('account_transactions', [
            'id' => $opening->id,
            'transaction_date' => '2026-03-15',
            'amount' => 250,
        ]);
    }

    public function test_tenant_opening_date_must_stay_between_previous_exit_and_first_activity(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Dates', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $previous = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Previous', 'is_active' => false]);
        $previous->tenantAssignments()->create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'move_in_date' => '2025-01-01', 'move_out_date' => '2026-02-28']);
        $account = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Current', 'is_active' => true,
            'account_opening_date' => '2026-03-01']);
        $assignment = $account->tenantAssignments()->create(['apartment_id' => $apartment->id,
            'unit_id' => $unit->id, 'move_in_date' => '2026-03-01']);
        $account->transactions()->create(['apartment_id' => $apartment->id, 'type' => 'debit',
            'amount' => 50, 'description' => 'First due', 'transaction_date' => '2026-03-10']);

        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->get(route('accounts.edit', $account))->assertOk()
            ->assertSee('min="2026-02-28"', false)->assertSee('max="2026-03-10"', false);
        foreach (['2026-02-27', '2024-01-01', '2026-03-11'] as $date) {
            $this->put(route('accounts.update', $account), ['name' => 'Current', 'unit_id' => $unit->id,
                'account_opening_date' => $date, 'is_active' => '1'])
                ->assertSessionHasErrors('account_opening_date');
            $this->assertSame('2026-03-01', $assignment->fresh()->move_in_date->toDateString());
        }
        foreach (['2026-02-28', '2026-03-05', '2026-03-10'] as $date) {
            $this->put(route('accounts.update', $account), ['name' => 'Current', 'unit_id' => $unit->id,
                'account_opening_date' => $date, 'is_active' => '1'])->assertSessionHasNoErrors();
            $this->assertSame($date, $assignment->fresh()->move_in_date->toDateString());
        }

        // A later tenancy must not become the predecessor when editing an earlier account.
        $later = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT, 'name' => 'Later', 'is_active' => false]);
        $later->tenantAssignments()->create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'move_in_date' => '2026-04-01', 'move_out_date' => '2026-05-01']);
        $this->assertSame('2026-02-28', $account->fresh()->previousTenantMoveOutDate());
    }

    public function test_opening_date_cannot_follow_first_activity(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Tarih Kontrolü',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Enerji',
            'account_opening_date' => '2026-01-01',
        ]);
        $opening = $account->transactions()->create([
            'apartment_id' => $apartment->id,
            'type' => 'debit',
            'description' => 'Açılış bakiyesi',
            'amount' => 100,
            'transaction_date' => '2026-01-01',
        ]);
        // Insert out of date order to ensure the earliest date, not ID, is used.
        foreach (['2026-03-20', '2026-03-10'] as $date) {
            $account->transactions()->create([
                'apartment_id' => $apartment->id,
                'type' => 'debit',
                'description' => 'Hesap hareketi',
                'amount' => 50,
                'transaction_date' => $date,
            ]);
        }

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertSee('max="2026-03-10"', false);

        $this->put(route('accounts.update', $account), [
            'name' => 'Değişmemeli',
            'account_opening_date' => '2026-03-11',
            'is_active' => '1',
        ])->assertSessionHasErrors('account_opening_date');

        $this->assertSame('Enerji', $account->fresh()->name);
        $this->assertSame('2026-01-01', $account->fresh()->account_opening_date->toDateString());
        $this->assertSame('2026-01-01', $opening->fresh()->transaction_date->toDateString());

        foreach (['2026-03-10', '2026-03-09', '2025-01-01'] as $date) {
            $this->put(route('accounts.update', $account), [
                'name' => 'Enerji',
                'account_opening_date' => $date,
                'is_active' => '1',
            ])->assertSessionHasNoErrors()->assertRedirect(route('accounts.show', $account));
            $this->assertSame($date, $account->fresh()->account_opening_date->toDateString());
            $this->assertSame($date, $opening->fresh()->transaction_date->toDateString());
        }
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

    public function test_user_can_create_tenant_account_with_opening_date(): void
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

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->get(route('accounts.create'))->assertOk()
            ->assertSee('name="account_opening_date"', false)
            ->assertDontSee('name="move_in_date"', false);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => Account::TYPE_TENANT,
                'unit_id' => $unit->id,
                'name' => 'Kiracı Ali',
                'account_opening_date' => '2026-05-01',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.index'));

        $tenant = Account::where('name', 'Kiracı Ali')->firstOrFail();

        $this->assertSame('2026-05-01', $tenant->account_opening_date->toDateString());
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
                'account_opening_date' => '2026-06-01',
                'balance' => 0,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_user_can_terminate_tenancy_using_termination_form(): void
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

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user)
            ->get(route('accounts.edit', $tenant))->assertOk()
            ->assertSee('name="termination_date"', false)
            ->assertSee('/terminate-tenancy', false)
            ->assertDontSee('name="move_out_date"', false);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->patch(route('accounts.terminate-tenancy', $tenant), [
                'termination_date' => '2026-06-01',
            ])
            ->assertRedirect(route('accounts.show', $tenant));

        $this->assertSame('2026-06-01', TenantAssignment::where('account_id', $tenant->id)->firstOrFail()->move_out_date->format('Y-m-d'));
        $this->assertSame($owner->id, $unit->fresh()->occupant_account_id);
        $this->assertFalse((bool) $tenant->fresh()->is_active);
        $this->assertSame('2026-06-01', $tenant->fresh()->account_end_date->toDateString());
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
                'account_opening_date' => null,
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

    public function test_passive_account_detail_shows_transfer_button_for_open_dues(): void
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
        $inactiveTenant = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_TENANT,
            'name' => 'Eski Kiracı',
            'is_active' => false,
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $inactiveTenant))
            ->assertStatus(200)
            ->assertSee('Borç Devri')
            ->assertSee('Kat Maliki');
    }

    public function test_account_detail_shows_transfer_button_for_imported_dues(): void
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
            'name' => 'Kiracı',
            'is_active' => true,
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
            'is_imported' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $tenant))
            ->assertStatus(200)
            ->assertSee('Borç Devri')
            ->assertSee('Devir Öncesi');
    }

    public function test_partial_due_is_shown_but_disabled_in_transfer_modal(): void
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
            'name' => 'Kiracı',
            'is_active' => true,
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        $partialDue = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 200,
            'due_date' => '2026-05-31',
            'status' => 'partial',
        ]);
        $unpaidDue = Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'account_id' => $tenant->id,
            'category_id' => $category->id,
            'period' => '2026-06',
            'amount' => 300,
            'remaining_amount' => 300,
            'due_date' => '2026-06-30',
            'status' => 'unpaid',
        ]);

        $response = $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $tenant));

        $response->assertStatus(200)
            ->assertSee('Borç Devri')
            ->assertSee('Kısmen ödenmiş aidat devredilemez')
            ->assertSee('disabled');
    }

    public function test_passive_account_detail_with_mismatched_unit_id_shows_transfer_button(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 2,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unitOne = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        $unitTwo = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '2',
        ]);
        $inactiveTenant = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unitTwo->id,
            'type' => Account::TYPE_TENANT,
            'name' => 'Eski Kiracı',
            'is_active' => false,
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unitOne->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'is_active' => true,
        ]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'type' => Category::TYPE_INCOME]);
        Due::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unitOne->id,
            'account_id' => $inactiveTenant->id,
            'category_id' => $category->id,
            'period' => '2026-05',
            'amount' => 500,
            'remaining_amount' => 500,
            'due_date' => '2026-05-31',
            'status' => 'unpaid',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.show', $inactiveTenant))
            ->assertStatus(200)
            ->assertSee('Borç Devri')
            ->assertSee('Kat Maliki');
    }

    public function test_expense_modal_can_create_supplier_via_ajax(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => 'supplier',
                'apartment_id' => $apartment->id,
                'name' => 'A Enerji',
                'default_category_id' => '',
                'phone' => '',
                'email' => '',
                'account_opening_date' => '2026-10-10',
                'balance' => '0',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('account.name', 'A Enerji');

        $this->assertDatabaseHas('accounts', [
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'A Enerji',
        ]);
    }

    public function test_supplier_name_can_be_reused_after_soft_delete(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $existing = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'A Enerji',
        ]);
        $existing->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => 'supplier',
                'name' => 'A Enerji',
                'account_opening_date' => '2026-10-10',
                'balance' => '0',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('account.name', 'A Enerji');
    }

    public function test_ajax_supplier_validation_returns_field_message(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'A Enerji',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.store'), [
                'type' => 'supplier',
                'name' => 'A Enerji',
                'account_opening_date' => '2026-10-10',
            ], [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bu isimde bir hesap zaten mevcut.');
    }

    public function test_account_statement_excel_and_pdf_are_free(): void
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
            'type' => Account::TYPE_OWNER,
            'name' => 'Salih Sayan',
        ]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Ocak aidatı',
            'amount' => 150,
            'transaction_date' => '2026-01-15',
        ]);

        $session = [CurrentApartment::SESSION_KEY => $apartment->id];

        $this->withSession($session)
            ->actingAs($user)
            ->get(route('accounts.statement', ['id' => $account->id, 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()
            ->assertSee('statement/export/excel', false)
            ->assertSee('statement/export/pdf', false)
            ->assertSee('Excel')
            ->assertSee('h-4 w-4 shrink-0 text-green-600', false)
            ->assertSee('h-4 w-4 shrink-0 text-red-500', false)
            ->assertDontSee('hidden h-4 w-4 shrink-0 text-green-600', false)
            ->assertSee('Salih Sayan (Kat Maliki)', false)
            ->assertSee('Daire 01')
            ->assertSee('Tüm Hareketler')
            ->assertSee('Seçilen tarih aralığındaki borç, alacak ve bakiye hareketleri.')
            ->assertSee('Filtrele')
            ->assertSee('Temizle')
            ->assertSee(route('accounts.show', $account), false)
            ->assertSee('PDF')
            ->assertSee('+ Borçlandır')
            ->assertSee('+ Tahsilat Ekle')
            ->assertSee('Düzenle')
            ->assertDontSee("Excel'e Aktar");

        $this->withSession($session)
            ->actingAs($user)
            ->get(route('accounts.statement.export', ['id' => $account->id, 'type' => 'excel', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $html = view('accounts.statement-pdf', [
            'account' => $account->load('unit'),
            'apartment' => $apartment,
            'heading' => 'Daire: 01 - Salih Sayan (Kat Maliki)',
            'transactions' => $account->transactions,
            'dateFrom' => '2026-01-01',
            'dateTo' => '2026-01-31',
            'openingBalance' => 0,
            'closingBalance' => 150,
            'summary' => 'Hesabın Toplam 150,00 TL borcu vardır',
        ])->render();

        $this->assertStringContainsString('Daire: 01 - Salih Sayan (Kat Maliki)', $html);
        $this->assertStringContainsString('Hesabın Toplam 150,00 TL borcu vardır', $html);
        $this->assertStringContainsString('Ocak aidatı', $html);
    }

    public function test_account_statement_paginates_like_the_dues_list(): void
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
            'type' => Account::TYPE_OWNER,
            'name' => 'Salih Sayan',
        ]);

        foreach (range(0, 25) as $day) {
            AccountTransaction::create([
                'apartment_id' => $apartment->id,
                'account_id' => $account->id,
                'type' => 'debit',
                'description' => 'Hareket '.$day,
                'amount' => 10,
                'transaction_date' => now()->parse('2026-01-01')->addDays($day)->toDateString(),
            ]);
        }

        $session = [CurrentApartment::SESSION_KEY => $apartment->id];
        $query = ['id' => $account->id, 'date_from' => '2026-01-01', 'date_to' => '2026-02-15'];

        $this->withSession($session)
            ->actingAs($user)
            ->get(route('accounts.statement', $query))
            ->assertOk()
            ->assertSee('26 kayıt')
            ->assertSee('Dönem Açılış Bakiyesi')
            ->assertSee('250,00 TL')
            ->assertSee('260,00 TL')
            ->assertSee('Sonraki')
            ->assertDontSee('Previous')
            ->assertDontSee('Next');

        $this->withSession($session)
            ->actingAs($user)
            ->get(route('accounts.statement', $query + ['page' => 2]))
            ->assertOk()
            ->assertSee('Hareket 25')
            ->assertSee('260,00 TL')
            ->assertSee('Önceki')
            ->assertDontSee('Dönem Açılış Bakiyesi')
            ->assertDontSee('Hareket 0')
            ->assertDontSee('Previous')
            ->assertDontSee('Next');
    }

    public function test_account_action_pages_use_the_statement_heading(): void
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
            'type' => Account::TYPE_OWNER,
            'name' => 'Salih Sayan',
        ]);

        $session = [CurrentApartment::SESSION_KEY => $apartment->id];

        foreach ([
            route('accounts.edit', $account),
            route('payments.create', ['account_id' => $account->id]),
        ] as $url) {
            $this->withSession($session)
                ->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSee('Daire 01')
                ->assertSee('Salih Sayan (Kat Maliki)', false)
                ->assertSee(route('accounts.show', $account), false);
        }
    }
}
