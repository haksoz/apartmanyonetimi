<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use App\Support\UserApartmentQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_user_cannot_log_in_until_the_link_sets_a_password(): void
    {
        [$manager, $apartment] = $this->managerApartment();

        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Davet Edilen',
            'email' => 'davet@example.com',
            'phone' => '5551112233',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('accounts.user.store', $account))
            ->assertRedirect(route('accounts.show', $account));

        $invited = User::query()->where('email', 'davet@example.com')->firstOrFail();
        $this->assertNull($invited->activated_at);
        $this->assertSame('user', $invited->role);
        $this->assertSame('member', $apartment->members()->whereKey($invited->id)->first()->pivot->role);

        $url = session('invite_url');
        $this->assertNotEmpty($url);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertSee('Kullanıcılar', false)
            ->assertSee('ekranından takip edin ve yönetin')
            ->assertSee('AidatCep Portal Erişimi')
            ->assertSee('Açık')
            ->assertDontSee('Şifre Belirle');

        $invited->update(['password' => 'bilinen-parola']);

        $this->post(route('logout'));

        $this->post('/login', [
            'email' => 'davet@example.com',
            'password' => 'bilinen-parola',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.password', $invited), ['password' => 'yonetici-yazar'])
            ->assertSessionHasErrors('password');

        $token = $this->tokenFrom($url);
        $this->get(route('invite.show', $token))
            ->assertOk()
            ->assertSee('Hesabınızı açın')
            ->assertSee('Gizlilik ve KVKK aydınlatmasını okudum, kabul ediyorum.');

        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
        ])->assertSessionHasErrors('accept_privacy');
        $this->assertNull($invited->fresh()->activated_at);

        $this->post(route('invite.accept', $token), [
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
            'accept_privacy' => '1',
        ])->assertRedirect(route('subscriber.dashboard'));

        $this->assertAuthenticatedAs($invited);
        $this->assertNotNull($invited->fresh()->activated_at);
        $this->assertTrue(Hash::check('yeni-parola', $invited->fresh()->password));
        $this->assertNotNull(UserInvitation::query()->where('user_id', $invited->id)->first()->accepted_at);
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $invited->id,
            'document_key' => LegalConsent::PRIVACY,
        ]);
        $this->assertDatabaseMissing('legal_acceptances', [
            'user_id' => $invited->id,
            'document_key' => LegalConsent::MEMBERSHIP,
        ]);
    }

    public function test_refreshing_an_invite_drops_the_previous_link(): void
    {
        [$manager, $apartment] = $this->managerApartment();
        $invited = User::factory()->create([
            'role' => 'user',
            'activated_at' => null,
            'email' => 'eski@example.com',
        ]);
        $apartment->members()->attach($invited->id, ['role' => 'member', 'is_active' => true]);

        $first = UserInvitation::issue($invited);

        $response = $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->from(route('users.index'))
            ->followingRedirects()
            ->post(route('users.invite', $invited));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'Yeni davet bağlantısı hazır.'));
        preg_match('#/davet/([^"\s<]+)#', $response->getContent(), $invite);
        $second = $invite[1];
        $this->assertNotSame($first, $second);
        $this->assertSame(1, UserInvitation::query()->where('user_id', $invited->id)->count());

        auth()->logout();

        $this->post(route('invite.accept', $first), [
            'password' => 'eski-parola',
            'password_confirmation' => 'eski-parola',
        ])->assertRedirect(route('invite.show', $first));
        $this->assertGuest();
        $this->assertNull($invited->fresh()->activated_at);

        $expired = UserInvitation::query()->where('user_id', $invited->id)->firstOrFail();
        $expired->update(['expires_at' => now()->subMinute()]);

        $this->get(route('invite.show', $second))
            ->assertOk()
            ->assertSee('geçersiz veya süresi dolmuş');
    }

    public function test_a_member_cannot_open_subscriptions_or_create_an_apartment(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $apartment = Apartment::factory()->create(['is_active' => true, 'unit_count' => 8]);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);

        $this->actingAs($member)
            ->get(route('subscriber.subscriptions.create'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('subscriber.apartments.store'), [
                'name' => 'Uye Apartmani',
                'address' => 'Adres',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertForbidden();

        $loose = User::factory()->create(['role' => 'user']);

        $this->actingAs($loose)
            ->post(route('onboarding.store'), [
                'name' => 'Izinsiz Apartman',
                'unit_count' => 4,
                'manager_type' => 'external',
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertSessionHasErrors('name');

        $this->assertSame(0, Apartment::query()->where('user_id', $loose->id)->count());
        $this->assertFalse(app(UserApartmentQuota::class)->canCreate($loose));
    }

    public function test_a_promoted_owner_can_order_the_next_subscription_without_opening_a_new_apartment(): void
    {
        [$manager, $apartment] = $this->managerApartment();
        $next = User::factory()->create([
            'role' => 'user',
            'name' => 'Sonraki Yonetici',
            'activated_at' => now(),
        ]);
        $apartment->members()->attach($next->id, ['role' => 'member', 'is_active' => true]);

        $previous = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'order_number' => 'SIP-26-ESKI1',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        app(SubscriptionCheckout::class)->openFree($manager, $apartment);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $next), ['role' => 'owner'])
            ->assertRedirect();

        $next->refresh();
        $this->assertSame('user', $next->role);
        $this->assertSame('member', $apartment->members()->whereKey($next->id)->first()->pivot->role);

        $offer = ManagementOffer::query()->where('user_id', $next->id)->where('status', ManagementOffer::STATUS_PENDING)->firstOrFail();

        $this->actingAs($next)
            ->post(route('management-offers.accept', $offer), [
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertRedirect(route('subscriber.dashboard'));

        $this->assertSame('owner', $apartment->members()->whereKey($next->id)->first()->pivot->role);

        $this->actingAs($next)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('id="legal-consent-prompt"', false)
            ->assertSee('Üyelik sözleşmesini okudum, kabul ediyorum.');

        $this->actingAs($next)
            ->get(route('legal.membership.accept'))
            ->assertOk()
            ->assertSee('Üyelik sözleşmesini okudum, kabul ediyorum.');

        $this->actingAs($next)
            ->from(route('subscriber.subscriptions.create'))
            ->post(route('legal.membership.accept.store'))
            ->assertSessionHasErrors('accept_membership');

        $this->actingAs($next)
            ->from(route('subscriber.subscriptions.create'))
            ->post(route('legal.membership.accept.store'), ['accept_membership' => '1'])
            ->assertRedirect(route('subscriber.subscriptions.create'));

        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $next->id,
            'document_key' => LegalConsent::MEMBERSHIP,
        ]);

        $this->actingAs($next)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee($apartment->name);

        $this->actingAs($next)
            ->post(route('subscriber.apartments.store'), [
                'name' => 'Ikinci Bina',
                'address' => 'Adres',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertForbidden();

        $this->actingAs($next)
            ->post(route('onboarding.store'), [
                'name' => 'Ikinci Bina',
                'unit_count' => 4,
                'manager_type' => 'external',
            ])->assertRedirect(route('dashboard'));

        $this->actingAs($next)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
            ] + $this->billingSelection($next))
            ->assertRedirect();

        $order = UserSubscription::query()->where('user_id', $next->id)->where('status', UserSubscription::STATUS_PENDING)->first();
        $this->assertNotNull($order);
        $this->assertSame($apartment->id, $order->items()->first()->apartment_id);
        $this->assertSame($manager->id, $previous->fresh()->user_id);
        $this->assertSame('SIP-26-ESKI1', $previous->fresh()->order_number);
        $this->assertSame(0, Apartment::query()->where('user_id', $next->id)->count());
    }

    public function test_admin_can_let_a_non_subscriber_create_by_setting_a_quota(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        UserQuotaOverride::query()->create([
            'user_id' => $user->id,
            'max_apartments' => 1,
        ]);

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), [
                'name' => 'Kapali Kapi',
                'address' => 'Adres',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertForbidden();

        $this->actingAs($user)
            ->post(route('onboarding.store'), [
                'name' => 'Kotali Apartman',
                'unit_count' => 4,
                'manager_type' => 'external',
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])->assertRedirect();

        $this->assertSame(1, Apartment::query()->where('user_id', $user->id)->where('is_active', true)->count());
    }

    public function test_customer_list_defaults_to_commercial_counterparts(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Kayitli Musteri', 'role' => 'manager']);
        $member = User::factory()->create([
            'name' => 'Sadece Uye',
            'role' => 'user',
            'activated_at' => now(),
        ]);
        $invitee = User::factory()->create([
            'name' => 'Davet Bekleyen',
            'role' => 'user',
            'activated_at' => null,
        ]);
        $owner = User::factory()->create([
            'name' => 'Bina Yoneticisi',
            'role' => 'user',
            'activated_at' => now(),
        ]);
        $apartment = Apartment::factory()->forUser($customer)->create(['is_active' => true]);
        $apartment->members()->attach($customer->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Muhataplar')
            ->assertSee('Kayitli Musteri')
            ->assertSee('Bina Yoneticisi')
            ->assertDontSee('Sadece Uye')
            ->assertDontSee('Davet Bekleyen');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['filtre' => 'uyeler']))
            ->assertOk()
            ->assertSee('Sadece Uye')
            ->assertSee('Üye')
            ->assertDontSee('Davet Bekleyen')
            ->assertDontSee('Kayitli Musteri')
            ->assertDontSee('Bina Yoneticisi');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['filtre' => 'davetliler']))
            ->assertOk()
            ->assertSee('Davet Bekleyen')
            ->assertSee('Davetli')
            ->assertDontSee('Sadece Uye')
            ->assertDontSee('Kayitli Musteri');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['filtre' => 'tumu']))
            ->assertOk()
            ->assertSee('Kayitli Musteri')
            ->assertSee('Sadece Uye')
            ->assertSee('Davet Bekleyen')
            ->assertSee('Bina Yoneticisi');
    }

    public function test_admin_cannot_impersonate_an_unactivated_user(): void
    {
        $admin = User::factory()->admin()->create();
        $invitee = User::factory()->create([
            'role' => 'user',
            'activated_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', $invitee))
            ->assertForbidden();
    }

    public function test_missing_kvkk_approval_is_collected_in_a_popup(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="legal-consent-prompt"', false)
            ->assertSee('Gizlilik ve KVKK aydınlatmasını okudum, kabul ediyorum.')
            ->assertDontSee('Üyelik sözleşmesini okudum, kabul ediyorum.');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('legal.membership.accept.store'), ['accept_privacy' => '1'])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_key' => LegalConsent::PRIVACY,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('id="legal-consent-prompt"', false);
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function managerApartment(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Davet Apartmani',
            'unit_count' => 10,
            'is_active' => true,
            'billing_plan' => 'free',
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        return [$manager, $apartment];
    }

    private function tokenFrom(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }
}
