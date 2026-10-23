<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspension_requires_reason_and_future_expiry_and_does_not_affect_other_accounts(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $other = $this->account($apartment, $member, '2');
        $this->transaction($account, 'Gizli borc', '2026-10-10', 'debit');
        $this->transaction($other, 'Diger daire borcu', '2026-10-10', 'debit');
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->patch(route('accounts.user.access', $account), ['action' => 'suspend'])
            ->assertSessionHasErrors(['reason', 'suspended_until']);
        $this->patch(route('accounts.user.access', $account), [
            'action' => 'suspend', 'reason' => 'Kontrol bekleniyor', 'suspended_until' => '2020-01-01T10:00',
        ])->assertSessionHasErrors('suspended_until');
        $until = now()->timezone('Europe/Istanbul')->addDays(2)->startOfMinute();
        $this->patch(route('accounts.user.access', $account), [
            'action' => 'suspend', 'reason' => 'Kontrol bekleniyor', 'suspended_until' => $until->format('Y-m-d\TH:i'),
        ])->assertRedirect();
        $this->assertTrue($account->fresh()->portalIsSuspended());
        $this->assertSame($until->utc()->toDateTimeString(), $account->fresh()->portal_suspended_until->toDateTimeString());
        $this->assertFalse($other->fresh()->portalIsSuspended());
        $this->assertSame($member->id, $account->fresh()->user_id);
        $this->assertNull($member->fresh()->deactivated_at);
        $this->assertDatabaseHas('account_portal_access_events', [
            'account_id' => $account->id, 'performed_by' => $manager->id, 'user_id' => $member->id,
            'action' => 'suspend', 'reason' => 'Kontrol bekleniyor',
        ]);

        $this->actingAs($member)->get(route('residence.account', $account))->assertOk()
            ->assertSee('Kontrol bekleniyor')->assertSee('geçici olarak durduruldu')->assertDontSee('Gizli borc')->assertDontSee('Bakiye');
        $this->get(route('residence.account', $other))->assertOk()->assertSee('Diger daire borcu');
        $this->get(route('reports.account-statement', ['account_id' => $account->id]))->assertForbidden();
        $this->get(route('reports.account-statement.export', ['type' => 'excel', 'account_id' => $account->id]))->assertForbidden();
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $member->email, 'password' => 'password'])->assertRedirect(route('subscriber.dashboard'));
        $this->get(route('subscriber.dashboard'))->assertOk()->assertSee('Erişim geçici durduruldu');

        $this->travel(3)->days();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.account', $account))->assertOk()->assertSee('Gizli borc')->assertDontSee('geçici olarak durduruldu');
        $this->assertFalse($account->fresh()->portalIsSuspended());
    }

    public function test_manager_can_restore_access_early_and_actions_are_audited(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $account->update(['portal_suspended_until' => now()->addDays(5), 'portal_suspension_reason' => 'Kontrol']);
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('accounts.user.access', $account), ['action' => 'resume', 'reason' => 'Kontrol tamamlandi'])->assertRedirect();
        $this->assertFalse($account->fresh()->portalIsSuspended());
        $this->assertDatabaseHas('account_portal_access_events', ['account_id' => $account->id, 'action' => 'resume', 'reason' => 'Kontrol tamamlandi']);
    }

    public function test_ended_tenancy_keeps_history_and_later_payments_without_exposing_another_resident(): void
    {
        [$manager, $apartment, $member, $account] = $this->building(Account::TYPE_TENANT);
        TenantAssignment::create([
            'apartment_id' => $apartment->id, 'account_id' => $account->id, 'unit_id' => $account->unit_id,
            'move_in_date' => '2026-01-01',
        ]);
        $this->transaction($account, 'Kendi donem borcu', '2026-05-10', 'debit');
        $this->transaction($account, 'Donem sonrasi borc', '2026-09-10', 'debit');
        $this->transaction($account, 'Sonradan yapilan odeme', '2026-09-20', 'credit');
        $newMember = User::factory()->create(['role' => 'user']);
        $newAccount = $this->account($apartment, $newMember, '1');
        $this->transaction($newAccount, 'Yeni sakinin ozel borcu', '2026-09-10', 'debit');

        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('accounts.terminate-tenancy', $account), ['termination_date' => '2026-08-31'])->assertRedirect();
        $this->assertSame($member->id, $account->fresh()->user_id);
        $this->assertTrue($apartment->members()->whereKey($member->id)->exists());
        $this->get(route('users.index'))->assertOk()->assertDontSee($member->email);
        $this->get(route('users.index', ['durum' => 'eski']))->assertOk()->assertSee($member->email)->assertSee('Kiracılığı sonlanmış');

        $this->actingAs($member)->get(route('subscriber.dashboard'))->assertOk()->assertSee('Kiracılığı sonlanmış');
        $this->post(route('current-apartment.update'), ['apartment_id' => $apartment->id, 'destination' => 'dairem', 'account_id' => $account->id])
            ->assertRedirect(route('residence.account', $account));
        $this->get(route('residence.account', $account))->assertOk()->assertSee('Kendi donem borcu')
            ->assertSee('Sonradan yapilan odeme')->assertDontSee('Donem sonrasi borc')->assertDontSee('Yeni sakinin ozel borcu');
        $this->get(route('reports.account-statement', ['account_id' => $account->id, 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']))
            ->assertOk()->assertSee('Kendi donem borcu')->assertSee('Sonradan yapilan odeme')->assertDontSee('Donem sonrasi borc');
        $this->get(route('residence.account', $newAccount))->assertForbidden();
        $this->get(route('reports.account-statement', ['account_id' => $newAccount->id]))->assertForbidden();
    }

    public function test_ended_ownership_retains_old_account_and_new_owner_gets_a_separate_account(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $account->unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('accounts.terminate-ownership', $account), ['termination_date' => '2026-08-31'])->assertRedirect();
        $this->assertSame($member->id, $account->fresh()->user_id);
        $this->assertTrue($account->fresh()->residenceHasEnded());
        $newOwner = Account::findOrFail($account->unit->fresh()->owner_account_id);
        $this->assertNotSame($newOwner->id, $account->id);
        $this->assertNull($newOwner->user_id);
        $this->actingAs($member)->get(route('subscriber.dashboard'))->assertOk()->assertSee('Kat malikliği sonlanmış');
    }

    public function test_a_person_with_another_active_account_or_management_stays_in_the_active_list(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $account->update(['is_active' => false, 'account_end_date' => '2026-08-31']);
        $other = $this->account($apartment, $member, '2');
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->get(route('users.index'))->assertOk()->assertSee($member->email);
        $this->get(route('users.index', ['durum' => 'eski']))->assertOk()->assertDontSee($member->email);
        $other->update(['is_active' => false]);
        $apartment->members()->updateExistingPivot($member->id, ['role' => 'owner']);
        $this->get(route('users.index'))->assertOk()->assertSee($member->email);
    }

    public function test_wrong_link_removal_requires_reason_notifies_user_and_preserves_history(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $tx = $this->transaction($account, 'Saklanan islem', '2026-10-10', 'debit');
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->delete(route('accounts.user.destroy', $account))->assertSessionHasErrors(['action', 'reason']);
        $this->assertSame($member->id, $account->fresh()->user_id);
        $this->delete(route('accounts.user.destroy', $account), ['action' => 'unlink', 'reason' => 'Yanlis e-posta eslesmesi'])->assertRedirect();
        $this->assertNull($account->fresh()->user_id);
        $this->assertFalse($apartment->members()->whereKey($member->id)->exists());
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('account_transactions', ['id' => $tx->id]);
        $this->get(route('users.index'))->assertOk()->assertDontSee($member->email);
        $this->actingAs($member)->get(route('subscriber.dashboard'))->assertOk()->assertSee('Yanlis e-posta eslesmesi');
        $this->get(route('reports.account-statement', ['account_id' => $account->id]))->assertRedirect();
    }

    public function test_wrong_link_removal_preserves_other_links_and_management_membership(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $other = $this->account($apartment, $member, '2');
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->delete(route('accounts.user.destroy', $account), ['action' => 'unlink', 'reason' => 'Yanlis baglanti'])->assertRedirect();
        $this->assertTrue($apartment->members()->whereKey($member->id)->exists());
        $this->assertSame($member->id, $other->fresh()->user_id);
        $apartment->members()->updateExistingPivot($member->id, ['role' => 'owner']);
        $this->delete(route('accounts.user.destroy', $other), ['action' => 'unlink', 'reason' => 'Yanlis baglanti'])->assertRedirect();
        $this->assertTrue($apartment->members()->whereKey($member->id)->exists());
    }

    public function test_access_controls_cannot_be_used_by_a_resident_or_a_manager_of_another_building(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();
        $data = ['action' => 'suspend', 'reason' => 'Yetkisiz', 'suspended_until' => now()->addDay()->toDateTimeString()];
        $this->actingAs($member)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('accounts.user.access', $account), $data)->assertForbidden();
        [$outsider, $otherApartment] = $this->building();
        $this->actingAs($outsider)->withSession([CurrentApartment::SESSION_KEY => $otherApartment->id])
            ->patch(route('accounts.user.access', $account), $data)->assertForbidden();
        $this->delete(route('accounts.user.destroy', $account), ['action' => 'unlink', 'reason' => 'Yetkisiz'])->assertForbidden();
        $this->assertFalse($account->fresh()->portalIsSuspended());
    }

    private function building(string $type = Account::TYPE_OWNER): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $member = User::factory()->create(['role' => 'user', 'activated_at' => now()]);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Portal Apartmani', 'is_active' => true, 'billing_plan' => 'free', 'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);

        return [$manager, $apartment, $member, $this->account($apartment, $member, '1', $type)];
    }

    private function account(Apartment $apartment, User $member, string $number, string $type = Account::TYPE_OWNER): Account
    {
        $unit = Unit::firstOrCreate(['apartment_id' => $apartment->id, 'unit_no' => $number]);

        return Account::create([
            'apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'user_id' => $member->id, 'name' => $member->name,
            'email' => $member->email, 'type' => $type, 'is_active' => true, 'account_opening_date' => '2026-01-01',
        ]);
    }

    private function transaction(Account $account, string $description, string $date, string $type): AccountTransaction
    {
        return AccountTransaction::create([
            'apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'description' => $description, 'transaction_date' => $date, 'type' => $type, 'amount' => 100,
        ]);
    }
}
