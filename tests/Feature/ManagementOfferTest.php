<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Support\CurrentApartment;
use App\Support\LegalConsent;
use App\Support\UserApartmentQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_role_opens_a_pending_offer_and_acceptance_counts_toward_the_quota(): void
    {
        [$manager, $apartment] = $this->building();
        $member = $this->memberOf($apartment);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $member), ['role' => 'owner'])
            ->assertRedirect()
            ->assertSessionHas('management_offer_url');

        $this->assertSame('member', $apartment->members()->whereKey($member->id)->first()->pivot->role);
        $this->assertSame(0, app(UserApartmentQuota::class)->currentCount($member));

        $offer = ManagementOffer::query()->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Kabul bekliyor');

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('Kabul penceresini aç')
            ->assertDontSee('Apartmanımı AidatCep')
            ->assertDontSee('Abone Paneli');

        $this->actingAs($member)
            ->get(route('management-offers.show', $offer))
            ->assertOk()
            ->assertSee('Daire sakini verisi bildirimini');

        $this->actingAs($member)
            ->post(route('management-offers.accept', $offer), [
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertRedirect(route('subscriber.dashboard'));

        $this->assertSame('owner', $apartment->members()->whereKey($member->id)->first()->pivot->role);
        $this->assertSame(1, app(UserApartmentQuota::class)->currentCount($member));
        $this->assertSame(1, app(UserApartmentQuota::class)->currentCount($manager));
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $member->id,
            'apartment_id' => $apartment->id,
            'document_key' => LegalConsent::RESIDENT_DATA,
        ]);
    }

    public function test_a_second_pending_offer_is_not_opened_and_rejection_keeps_membership(): void
    {
        [$manager, $apartment] = $this->building();
        $member = $this->memberOf($apartment);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $member), ['role' => 'owner']);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $member), ['role' => 'owner'])
            ->assertSessionHas('status');

        $this->assertSame(1, ManagementOffer::query()->where('user_id', $member->id)->where('status', ManagementOffer::STATUS_PENDING)->count());

        $offer = ManagementOffer::query()->where('user_id', $member->id)->firstOrFail();

        $this->actingAs($member)
            ->post(route('management-offers.reject', $offer))
            ->assertRedirect(route('residence.home'));

        $this->assertSame('member', $apartment->members()->whereKey($member->id)->first()->pivot->role);
        $this->assertSame(0, app(UserApartmentQuota::class)->currentCount($member));
    }

    public function test_a_full_management_right_blocks_the_offer_and_asks_for_contact(): void
    {
        [$manager, $apartment] = $this->building();
        $member = $this->memberOf($apartment);
        $other = Apartment::factory()->create(['is_active' => true]);
        $other->members()->attach($member->id, ['role' => 'owner', 'is_active' => true]);
        UserQuotaOverride::query()->create([
            'user_id' => $member->id,
            'max_apartments' => 1,
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $member), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertSame('member', $apartment->members()->whereKey($member->id)->first()->pivot->role);
        $this->assertDatabaseHas('management_offers', [
            'apartment_id' => $apartment->id,
            'user_id' => $member->id,
            'status' => ManagementOffer::STATUS_BLOCKED,
        ]);
        $this->assertSame(0, ManagementOffer::query()->where('user_id', $member->id)->openPending()->count());

        app(LegalConsent::class)->recordMembership($member, request());

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee(UserApartmentQuota::CONTACT_MESSAGE)
            ->assertDontSee('Kabul penceresini aç');
    }

    public function test_a_raised_limit_allows_another_offer_and_an_unlimited_subscriber_can_take_more(): void
    {
        [$manager, $apartment] = $this->building();
        $member = $this->memberOf($apartment);
        $owned = Apartment::factory()->create(['is_active' => true]);
        $owned->members()->attach($member->id, ['role' => 'owner', 'is_active' => true]);
        UserQuotaOverride::query()->create([
            'user_id' => $member->id,
            'max_apartments' => 2,
        ]);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $member), ['role' => 'owner'])
            ->assertSessionHas('management_offer_url');

        $subscriber = User::factory()->create(['role' => 'manager']);
        $subscriberBuilding = Apartment::factory()->forUser($subscriber)->create(['is_active' => true]);
        $subscriberBuilding->members()->attach($subscriber->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($subscriber->id, ['role' => 'member', 'is_active' => true]);

        $this->assertTrue(app(UserApartmentQuota::class)->hasRoomForManagement($subscriber));

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $subscriber), ['role' => 'owner'])
            ->assertSessionHas('management_offer_url');
    }

    public function test_existing_owner_membership_counts_and_demotion_releases_it(): void
    {
        $opener = User::factory()->create();
        $apartment = Apartment::factory()->forUser($opener)->create(['is_active' => true, 'name' => 'Eyup']);
        $apartment->members()->attach($opener->id, ['role' => 'owner', 'is_active' => true]);
        $manager = User::factory()->create(['role' => 'user']);
        UserQuotaOverride::query()->create([
            'user_id' => $manager->id,
            'max_apartments' => 1,
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $quota = app(UserApartmentQuota::class);
        $this->assertSame(1, $quota->currentCount($opener));
        $this->assertSame(1, $quota->currentCount($manager));
        $this->assertNotSame($opener->id, $manager->id);

        $this->actingAs($opener)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.role', $manager), ['role' => 'member'])
            ->assertRedirect();

        $this->assertSame('member', $apartment->members()->whereKey($manager->id)->first()->pivot->role);
        $this->assertSame(0, $quota->currentCount($manager));
        $this->assertSame(1, $quota->currentCount($opener));
        $this->assertSame($opener->id, $apartment->fresh()->user_id);
    }

    public function test_a_member_opens_an_apartment_only_from_the_account_page(): void
    {
        [$manager, $apartment] = $this->building();
        $member = $this->memberOf($apartment);

        $this->actingAs($member)
            ->post(route('apartments.store'), [
                'name' => 'Izinsiz',
                'address' => 'Adres',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($member)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeInOrder([
                'Onaylanan belgeler',
                "AidatCep'te apartman yönetmek istiyorum",
                'Ücretsiz 1 apartman oluşturabilir ve yönetebilirsiniz.',
            ]);

        $this->actingAs($member)
            ->post(route('profile.open-apartment'))
            ->assertRedirect(route('apartments.create'));

        $this->actingAs($member)
            ->get(route('apartments.create'))
            ->assertOk()
            ->assertSee('Apartman Adı');

        $this->actingAs($member)
            ->post(route('apartments.store'), [
                'name' => 'Kendi Apartmanim',
                'address' => 'Adres',
                'unit_count' => 4,
                'account_opening_date' => now()->toDateString(),
                'accept_resident_data' => '1',
                'accept_privacy' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, app(UserApartmentQuota::class)->currentCount($member));
        $this->assertNotNull(Apartment::query()->where('user_id', $member->id)->where('name', 'Kendi Apartmanim')->first());

        $this->actingAs($manager)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee("AidatCep'te apartman yönetmek istiyorum");

        app(LegalConsent::class)->recordMembership($member, request());

        $this->actingAs($member)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee("AidatCep'te apartman yönetmek istiyorum");
    }

    public function test_registering_an_existing_email_sends_the_person_to_login_or_the_invite(): void
    {
        $member = User::factory()->create([
            'role' => 'user',
            'email' => 'ahmet@example.com',
        ]);

        $this->post(route('register'), [
            'name' => 'Ahmet',
            'email' => 'ahmet@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Bu e-posta ile hesabınız var, giriş yapın.')
            ->assertSessionHas('open_apartment_requested', true);

        $this->assertSame(1, User::query()->where('email', 'ahmet@example.com')->count());

        $this->post(route('login'), [
            'email' => 'ahmet@example.com',
            'password' => 'password',
        ])->assertRedirect(route('apartments.create'));

        $this->post(route('logout'));

        $invitee = User::factory()->create([
            'role' => 'user',
            'email' => 'davet@example.com',
            'activated_at' => null,
        ]);

        $this->post(route('register'), [
            'name' => 'Davet',
            'email' => $invitee->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'davet@example.com')->count());
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function building(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'B Apartmani',
            'is_active' => true,
            'unit_count' => 8,
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        return [$manager, $apartment];
    }

    private function memberOf(Apartment $apartment): User
    {
        $member = User::factory()->create(['role' => 'user']);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);

        return $member;
    }
}
