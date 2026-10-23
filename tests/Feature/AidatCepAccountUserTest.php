<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountPortalOffer;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserInvitation;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AidatCepAccountUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_account_without_email_cannot_grant_access(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $account->update(['email' => null]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->from(route('accounts.show', $account))
            ->post(route('accounts.user.store', $account))
            ->assertRedirect(route('accounts.show', $account))
            ->assertSessionHasErrors('email');

        $this->assertNull($account->fresh()->user_id);
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_same_email_links_the_existing_user_without_changing_their_name(): void
    {
        [$manager, $apartment, $first] = $this->ownerAccount(['email' => 'kisi@example.com', 'name' => 'Hesap Adi', 'phone' => '5551']);
        $existing = User::factory()->create([
            'name' => 'Gercek Ad',
            'email' => 'kisi@example.com',
            'phone' => '5559',
            'role' => 'user',
            'activated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $first))
            ->assertRedirect(route('accounts.show', $first))
            ->assertSessionMissing('invite_url');

        $this->assertNull($first->fresh()->user_id);
        $this->assertFalse($apartment->members()->whereKey($existing->id)->exists());

        $offer = AccountPortalOffer::query()->where('account_id', $first->id)->firstOrFail();

        $this->actingAs($existing)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Aidat Apartmani yöneticisi '.$manager->name.' sizi 1 no.lu dairenin kat maliki olarak davet etti.', false);

        $this->actingAs($existing)
            ->post(route('portal-offers.accept', $offer))
            ->assertRedirect();

        $this->assertSame($existing->id, $first->fresh()->user_id);
        $this->assertTrue($apartment->members()->whereKey($existing->id)->exists());

        $second = $this->account($apartment, ['email' => 'kisi@example.com', 'name' => 'Ikinci Hesap', 'unit_no' => '2']);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $second))
            ->assertRedirect(route('accounts.show', $second));

        $this->assertSame($existing->id, $second->fresh()->user_id);
        $this->assertSame(0, AccountPortalOffer::query()->where('status', AccountPortalOffer::STATUS_PENDING)->count());
        $this->assertSame(1, User::query()->where('email', 'kisi@example.com')->count());
        $this->assertSame('Gercek Ad', $existing->fresh()->name);
        $this->assertSame('5559', $existing->fresh()->phone);
        $this->assertSame('Hesap Adi', $first->fresh()->name);
        $this->assertSame('Ikinci Hesap', $second->fresh()->name);
    }

    public function test_rejecting_a_portal_offer_leaves_the_account_unlinked(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount([
            'email' => 'baska@example.com',
            'name' => 'Yanlis Hesap',
            'unit_no' => '9',
        ]);
        $existing = User::factory()->create([
            'email' => 'baska@example.com',
            'role' => 'user',
            'activated_at' => now(),
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account));

        $offer = AccountPortalOffer::query()->where('account_id', $account->id)->firstOrFail();

        $this->actingAs($existing)
            ->post(route('portal-offers.reject', $offer))
            ->assertRedirect();

        $this->assertNull($account->fresh()->user_id);
        $this->assertFalse($apartment->members()->whereKey($existing->id)->exists());
        $this->assertSame(AccountPortalOffer::STATUS_REJECTED, $offer->fresh()->status);
    }

    public function test_account_email_is_locked_after_the_invite_is_accepted(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount([
            'email' => 'davet@example.com',
            'name' => 'Eski Ad',
            'phone' => '5551',
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account));

        $this->acceptPendingInvite();
        $user = User::query()->where('email', 'davet@example.com')->firstOrFail();

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->from(route('accounts.edit', $account))
            ->put(route('accounts.update', $account), [
                'type' => Account::TYPE_OWNER,
                'unit_id' => $account->unit_id,
                'name' => 'Yeni Unvan',
                'phone' => '5552',
                'email' => 'baska@example.com',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.edit', $account))
            ->assertSessionHasErrors('email');

        $account->refresh();
        $this->assertSame('davet@example.com', $account->email);
        $this->assertSame('Eski Ad', $account->name);
        $this->assertSame('davet@example.com', $user->fresh()->email);
    }

    public function test_changing_the_email_invalidates_the_pending_invitation_without_creating_a_user(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount([
            'email' => 'eski@example.com',
            'name' => 'Hesap Adi',
            'phone' => '5551',
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account));

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->put(route('accounts.update', $account), [
                'type' => Account::TYPE_OWNER,
                'unit_id' => $account->unit_id,
                'name' => 'Hesap Adi',
                'phone' => '5551',
                'email' => 'yeni@example.com',
                'account_opening_date' => '2026-01-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('accounts.show', $account));

        $this->assertSame('yeni@example.com', $account->fresh()->email);
        $this->assertNull($account->fresh()->user_id);
        $this->assertDatabaseMissing('user_invitations', ['account_id' => $account->id]);
        $this->assertDatabaseMissing('users', ['email' => 'eski@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'yeni@example.com']);
    }

    public function test_profile_changes_show_on_the_user_list_until_a_manager_copies_them(): void
    {
        [$manager, $apartment, $first] = $this->ownerAccount([
            'email' => 'sakin@example.com',
            'name' => 'Defter Adi',
            'phone' => '5551',
        ]);
        $second = $this->account($apartment, [
            'email' => 'diger@example.com',
            'name' => 'Ikinci Unvan',
            'phone' => '5552',
            'unit_no' => '2',
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $first));

        $this->acceptPendingInvite();
        $member = User::query()->where('email', 'sakin@example.com')->firstOrFail();
        $second->update(['user_id' => $member->id]);

        $this->actingAs($member)
            ->patch(route('profile.update'), [
                'name' => 'Duzenlenmis Ad',
                'phone' => '5559',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Duzenlenmis Ad')
            ->assertSee('5559')
            ->assertDontSee('Yeni Kullanıcı Oluştur')
            ->assertSee('ilgili hesabın detayından eklenir');

        $this->assertSame('Defter Adi', $first->fresh()->name);
        $this->assertSame('5551', $first->fresh()->phone);
        $this->assertSame('Ikinci Unvan', $second->fresh()->name);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.identity', $first))
            ->assertRedirect();

        $this->assertSame('Duzenlenmis Ad', $first->fresh()->name);
        $this->assertSame('5559', $first->fresh()->phone);
        $this->assertSame('sakin@example.com', $first->fresh()->email);
        $this->assertSame('Ikinci Unvan', $second->fresh()->name);
        $this->assertSame('5552', $second->fresh()->phone);
    }

    private function acceptPendingInvite(): void
    {
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->post(route('logout'));
        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
            'accept_privacy' => '1',
        ])->assertRedirect();
    }

    public function test_cancelling_a_pending_invite_creates_no_user_and_invalidates_the_link(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account))->assertSessionHas('invite_url');
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->assertDatabaseMissing('users', ['email' => $account->email]);
        $this->get(route('users.index'))->assertOk()->assertDontSee($account->email);
        $this->delete(route('accounts.user.destroy', $account))->assertRedirect();
        $this->get(route('accounts.show', $account))->assertOk()->assertSee('Kapalı')->assertDontSee('Kabul bekliyor');
        $this->post(route('logout'));
        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola', 'password_confirmation' => 'yeni-parola', 'accept_privacy' => '1',
        ])->assertSessionHasErrors('token');
        $this->assertDatabaseMissing('users', ['email' => $account->email]);
        $this->assertNull($account->fresh()->user_id);
    }

    public function test_pending_invite_can_be_copied_and_regenerated_from_the_account_page(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account))->assertRedirect(route('accounts.show', $account));
        $firstUrl = session('invite_url');
        $firstToken = basename(parse_url($firstUrl, PHP_URL_PATH));

        $this->get(route('accounts.show', $account))->assertOk()
            ->assertSee('Kabul bekliyor')->assertSee('Kopyala')
            ->assertSee('value="'.$firstUrl.'"', false)
            ->assertSee('Yeni davet bağlantısı oluştur');

        $this->get(route('accounts.show', $account))->assertOk()
            ->assertDontSee($firstUrl, false)
            ->assertSee('Yeni davet bağlantısı oluştur');
        $this->assertNotNull(UserInvitation::findValid($firstToken));

        $this->post(route('accounts.user.invite', $account))->assertRedirect(route('accounts.show', $account));
        $secondUrl = session('invite_url');
        $this->assertNotSame($firstUrl, $secondUrl);
        $this->get(route('accounts.show', $account))->assertOk()
            ->assertSee('value="'.$secondUrl.'"', false)->assertSee('Kopyala');
        $this->assertNull(UserInvitation::findValid($firstToken));
        $this->assertDatabaseMissing('users', ['email' => $account->email]);
    }

    public function test_refresh_expiry_and_replay_of_account_invitations(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account));
        $first = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->post(route('accounts.user.invite', $account))->assertSessionHas('invite_url');
        $second = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->assertNotSame($first, $second);
        $this->assertNull(UserInvitation::findValid($first));
        $invite = $account->portalInvitations()->firstOrFail();
        $invite->update(['expires_at' => now()->subMinute()]);
        $this->assertNull(UserInvitation::findValid($second));
        $this->post(route('accounts.user.invite', $account));
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->acceptPendingInvite();
        $user = $account->fresh()->user;
        $this->assertNotNull($user->activated_at);
        $this->post(route('invite.accept', $token), [
            'password' => 'baska-parola', 'password_confirmation' => 'baska-parola', 'accept_privacy' => '1',
        ])->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('yeni-parola', $user->fresh()->password));
    }

    public function test_a_user_registered_after_the_invite_must_log_in_without_resetting_their_password(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account));
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $existing = User::factory()->create([
            'email' => $account->email, 'password' => 'mevcut-parola', 'activated_at' => now(), 'role' => 'user',
        ]);
        $this->post(route('logout'));
        $this->get(route('invite.show', $token))->assertOk()->assertSee('Oturum aç')->assertDontSee('id="password"', false);
        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola', 'password_confirmation' => 'yeni-parola', 'accept_privacy' => '1',
        ])->assertSessionHasErrors('token');
        $this->assertNull($account->fresh()->user_id);
        $this->actingAs($existing)->post(route('invite.accept', $token), ['accept_privacy' => '1'])->assertRedirect();
        $this->assertSame($existing->id, $account->fresh()->user_id);
        $this->assertTrue(Hash::check('mevcut-parola', $existing->fresh()->password));
    }

    public function test_legacy_pending_resident_is_converted_preserving_the_invitation(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $legacy = User::factory()->create(['email' => $account->email, 'role' => 'user', 'activated_at' => null]);
        $account->update(['user_id' => $legacy->id]);
        $apartment->members()->attach($legacy->id, ['role' => 'member', 'is_active' => true]);
        $token = UserInvitation::issue($legacy);
        $migration = require database_path('migrations/2026_10_23_120100_convert_pending_account_users_to_invitations.php');
        $migration->up();
        $this->assertDatabaseMissing('users', ['id' => $legacy->id]);
        $this->assertNull($account->fresh()->user_id);
        $this->assertFalse($apartment->members()->whereKey($legacy->id)->exists());
        $this->assertSame($account->id, UserInvitation::findValid($token)->account_id);
        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola', 'password_confirmation' => 'yeni-parola', 'accept_privacy' => '1',
        ])->assertRedirect();
        $this->assertNotNull($account->fresh()->user_id);
    }

    public function test_legacy_users_with_other_history_are_preserved(): void
    {
        [$manager, $apartment, $account] = $this->ownerAccount();
        $legacy = User::factory()->create(['email' => $account->email, 'role' => 'user', 'activated_at' => null]);
        $account->update(['user_id' => $legacy->id]);
        $other = Apartment::factory()->forUser($legacy)->create();
        $migration = require database_path('migrations/2026_10_23_120100_convert_pending_account_users_to_invitations.php');
        $migration->up();
        $this->assertDatabaseHas('users', ['id' => $legacy->id]);
        $this->assertSame($legacy->id, $account->fresh()->user_id);
        $this->assertDatabaseHas('apartments', ['id' => $other->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Apartment, 2: Account}
     */
    private function ownerAccount(array $overrides = []): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Aidat Apartmani',
            'unit_count' => 10,
            'is_active' => true,
            'billing_plan' => 'free',
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        return [$manager, $apartment, $this->account($apartment, $overrides)];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function account(Apartment $apartment, array $overrides = []): Account
    {
        $unitNo = $overrides['unit_no'] ?? '1';
        unset($overrides['unit_no']);

        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => $unitNo,
        ]);

        return Account::create(array_merge([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Kat Maliki',
            'email' => 'malik@example.com',
            'phone' => '5550000000',
            'is_active' => true,
        ], $overrides));
    }
}
