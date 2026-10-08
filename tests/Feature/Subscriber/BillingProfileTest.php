<?php

namespace Tests\Feature\Subscriber;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\FeatureGate;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_purchase_stores_the_profile_on_the_subscription_and_a_snapshot_on_the_order(): void
    {
        [$ahmet, $apartment, $subscription] = $this->apartmentFor('Ahmet');
        $profile = $this->profile($ahmet, 'Profil A', 'A Apartmanı Yönetimi');

        $order = $this->place($ahmet, $apartment, $profile);

        $subscription->refresh();
        $this->assertSame($profile->id, $subscription->billing_profile_id);
        $this->assertSame($profile->id, $order->billing_profile_id);
        $this->assertSame('Profil A', $order->billing_label);
        $this->assertSame('corporate', $order->billing_party_type);
        $this->assertSame('A Apartmanı Yönetimi', $order->billing_legal_name);
        $this->assertSame('1234567890', $order->billing_identity_number);
        $this->assertSame('Kadıköy', $order->billing_tax_office);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('A Apartmanı Yönetimi')
            ->assertSee('Profil A');
    }

    public function test_renewal_preselects_the_last_profile_and_a_new_choice_replaces_only_the_new_order(): void
    {
        [$ahmet, $apartment, $subscription] = $this->apartmentFor('Ahmet');
        $profileA = $this->profile($ahmet, 'Profil A', 'A Apartmanı Yönetimi');
        $profileB = $this->profile($ahmet, 'Profil B', 'ABC Ltd.');
        $first = $this->place($ahmet, $apartment, $profileA);
        $frozen = $this->snapshot($first);

        $this->approve($ahmet, $first);
        $first->refresh();
        $this->assertSame($frozen, $this->snapshot($first));

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Profil A · A Apartmanı Yönetimi')
            ->assertSee('value="'.$profileA->id.'" selected', false)
            ->assertDontSee('value="'.$profileB->id.'" selected', false);

        $second = $this->place($ahmet, $apartment, $profileB);

        $subscription->refresh();
        $first->refresh();
        $this->assertSame($profileB->id, $subscription->billing_profile_id);
        $this->assertSame('ABC Ltd.', $second->billing_legal_name);
        $this->assertSame('Profil B', $second->billing_label);
        $this->assertSame($frozen, $this->snapshot($first));

        $profileA->update([
            'label' => 'Profil A değişti',
            'legal_name' => 'Yeni Unvan',
            'identity_number' => '999',
            'tax_office' => 'Beşiktaş',
        ]);

        $first->refresh();
        $second->refresh();
        $this->assertSame($frozen, $this->snapshot($first));
        $this->assertSame('ABC Ltd.', $second->billing_legal_name);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $first))
            ->assertOk()
            ->assertSee('A Apartmanı Yönetimi')
            ->assertDontSee('Yeni Unvan');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $first))
            ->assertOk()
            ->assertSee('A Apartmanı Yönetimi')
            ->assertDontSee('Yeni Unvan');

        $this->actingAs($ahmet)
            ->patch(route('subscriber.billing-profiles.active', $profileA))
            ->assertRedirect();

        $this->assertFalse($profileA->fresh()->is_active);
        $first->refresh();
        $this->assertSame($frozen, $this->snapshot($first));

        $this->actingAs($ahmet)
            ->post(route('subscriber.subscriptions.cancel', $second))
            ->assertRedirect();

        $second->refresh();
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $second->status);
        $this->assertSame('ABC Ltd.', $second->billing_legal_name);
        $this->assertSame('Profil B', $second->billing_label);
        $this->assertSame($profileB->id, $subscription->fresh()->billing_profile_id);
    }

    public function test_manager_change_keeps_the_old_snapshot_and_lets_the_new_manager_choose_their_own_profile(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profileA = $this->profile($ahmet, 'Ahmet Profil A', 'A Apartmanı Yönetimi');
        $first = $this->place($ahmet, $apartment, $profileA);
        $frozen = $this->snapshot($first);
        $this->approve($ahmet, $first);

        $mehmet = User::factory()->create(['name' => 'Mehmet']);
        $apartment->members()->updateExistingPivot($ahmet->id, ['role' => 'member', 'is_active' => false]);
        $apartment->members()->attach($mehmet->id, ['role' => 'owner', 'is_active' => true]);
        $profileC = $this->profile($mehmet, 'Mehmet Profil C', 'Mehmet Ltd.');

        $this->assertTrue(FeatureGate::allows($apartment->fresh(), 'auto_dues', $mehmet));

        $this->actingAs($mehmet)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Bu hizmet A Apartmanı için Ahmet tarafından satın alınmıştır.')
            ->assertDontSee('Ahmet Profil A')
            ->assertDontSee('A Apartmanı Yönetimi');

        $this->actingAs($mehmet)
            ->get(route('subscriber.billing-profiles.index'))
            ->assertOk()
            ->assertSee('Mehmet Profil C')
            ->assertDontSee('Ahmet Profil A')
            ->assertDontSee('A Apartmanı Yönetimi');

        $this->actingAs($mehmet)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Mehmet Profil C')
            ->assertDontSee('Ahmet Profil A')
            ->assertDontSee('A Apartmanı Yönetimi')
            ->assertDontSee('value="'.$profileC->id.'" selected', false);

        $this->actingAs($mehmet)
            ->get(route('subscriber.billing-profiles.edit', $profileA))
            ->assertNotFound();

        $this->actingAs($mehmet)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
                'billing_mode' => 'existing',
                'billing_profile_id' => $profileA->id,
            ])
            ->assertSessionHasErrors('billing_profile_id');

        $renewal = $this->place($mehmet, $apartment, $profileC);

        $first->refresh();
        $this->assertSame($ahmet->id, $first->user_id);
        $this->assertSame($frozen, $this->snapshot($first));
        $this->assertTrue($first->is_active);
        $this->assertTrue(FeatureGate::allows($apartment->fresh(), 'auto_dues', $mehmet));
        $this->assertSame($mehmet->id, $renewal->user_id);
        $this->assertSame($apartment->id, $renewal->items()->first()->apartment_id);
        $this->assertSame('Mehmet Ltd.', $renewal->billing_legal_name);
        $this->assertSame($profileC->id, $apartment->subscriptionItems()->first()->apartmentSubscription->fresh()->billing_profile_id);

        $this->actingAs($mehmet)
            ->get(route('subscriber.subscriptions.receipt', $first))
            ->assertForbidden();
    }

    public function test_inactive_profile_cannot_be_selected_for_a_new_order(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profile = $this->profile($ahmet, 'Profil A', 'A Apartmanı Yönetimi');
        $profile->update(['is_active' => false]);

        $this->actingAs($ahmet)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
                'billing_mode' => 'existing',
                'billing_profile_id' => $profile->id,
            ])
            ->assertSessionHasErrors('billing_profile_id');

        $this->assertSame(0, UserSubscription::query()->count());
    }

    public function test_subscriber_can_keep_a_profile_with_only_a_name_and_type(): void
    {
        $ahmet = User::factory()->create(['name' => 'Ahmet']);
        $other = User::factory()->create(['name' => 'Başka']);

        $this->actingAs($ahmet)
            ->post(route('subscriber.billing-profiles.store'), [
                'label' => 'Kendi adıma',
                'party_type' => BillingProfile::TYPE_INDIVIDUAL,
            ])
            ->assertRedirect(route('subscriber.billing-profiles.index'));

        $profile = BillingProfile::query()->firstOrFail();
        $this->assertSame($ahmet->id, $profile->user_id);
        $this->assertNull($profile->legal_name);
        $this->assertNull($profile->identity_number);
        $this->assertNull($profile->tax_office);
        $this->assertTrue($profile->is_active);

        $this->actingAs($other)
            ->get(route('subscriber.billing-profiles.index'))
            ->assertOk()
            ->assertDontSee('Kendi adıma');

        $this->actingAs($other)
            ->get(route('subscriber.billing-profiles.edit', $profile))
            ->assertNotFound();
    }

    public function test_a_new_profile_can_be_created_while_placing_the_order(): void
    {
        [$ahmet, $apartment, $subscription] = $this->apartmentFor('Ahmet');

        $this->actingAs($ahmet)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
                'billing_mode' => 'new',
                'billing_label' => 'Yeni profil',
                'billing_party_type' => BillingProfile::TYPE_OTHER,
                'billing_legal_name' => 'Serbest Kayıt',
            ])
            ->assertRedirect();

        $profile = BillingProfile::query()->where('user_id', $ahmet->id)->firstOrFail();
        $order = UserSubscription::query()->where('user_id', $ahmet->id)->firstOrFail();

        $this->assertSame('Yeni profil', $profile->label);
        $this->assertSame(BillingProfile::TYPE_OTHER, $profile->party_type);
        $this->assertSame($profile->id, $subscription->fresh()->billing_profile_id);
        $this->assertSame('Serbest Kayıt', $order->billing_legal_name);
        $this->assertSame('Yeni profil', $order->billing_label);
    }

    public function test_free_opening_and_complimentary_access_do_not_store_billing_details(): void
    {
        [$ahmet, $apartment, $subscription] = $this->apartmentFor('Ahmet');

        $this->assertNull($subscription->billing_profile_id);

        $complimentary = app(SubscriptionCheckout::class)->grantComplimentary($ahmet, $apartment, 2);

        $this->assertNull($complimentary->billing_profile_id);
        $this->assertNull($complimentary->billing_label);
        $this->assertNull($complimentary->billing_legal_name);
        $this->assertNull($subscription->fresh()->billing_profile_id);
    }

    /**
     * @return array{0: User, 1: Apartment, 2: Subscription}
     */
    private function apartmentFor(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'A Apartmanı',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $subscription = app(SubscriptionCheckout::class)->openFree($user, $apartment);

        return [$user, $apartment, $subscription];
    }

    private function profile(User $user, string $label, string $legalName): BillingProfile
    {
        return BillingProfile::query()->create([
            'user_id' => $user->id,
            'label' => $label,
            'party_type' => BillingProfile::TYPE_CORPORATE,
            'legal_name' => $legalName,
            'identity_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'is_active' => true,
        ]);
    }

    private function place(User $user, Apartment $apartment, BillingProfile $profile): UserSubscription
    {
        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
                'billing_mode' => 'existing',
                'billing_profile_id' => $profile->id,
            ])
            ->assertRedirect();

        return UserSubscription::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function approve(User $user, UserSubscription $order): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-FATURA',
            ])
            ->assertRedirect();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(UserSubscription $order): array
    {
        return $order->only([
            'billing_profile_id',
            'billing_party_type',
            'billing_label',
            'billing_legal_name',
            'billing_identity_number',
            'billing_tax_office',
            'billing_email',
            'billing_phone',
            'billing_country',
            'billing_province',
            'billing_district',
            'billing_address',
            'billing_postal_code',
        ]);
    }
}
