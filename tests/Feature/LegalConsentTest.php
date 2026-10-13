<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_stores_membership_acceptance_once(): void
    {
        $payload = [
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'human_answer' => 5,
        ];

        $this->withSession($this->challenge())
            ->post(route('register'), $payload)
            ->assertSessionHasErrors('accept_membership');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);

        $this->withSession($this->challenge())
            ->post(route('register'), $payload + ['accept_membership' => '1'])
            ->assertSessionHasErrors('accept_privacy');

        $this->assertDatabaseCount('users', 0);

        $this->withSession($this->challenge())
            ->post(route('register'), $payload + ['accept_membership' => '1', 'accept_privacy' => '1'])
            ->assertRedirect(route('subscriber.dashboard'));

        $user = User::where('email', 'manager@example.com')->firstOrFail();
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_key' => LegalConsent::MEMBERSHIP,
            'document_version' => LegalConsent::VERSIONS[LegalConsent::MEMBERSHIP],
            'user_subscription_id' => null,
        ]);
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'document_key' => LegalConsent::PRIVACY,
            'document_version' => LegalConsent::VERSIONS[LegalConsent::PRIVACY],
            'user_subscription_id' => null,
        ]);
        $this->assertSame(2, LegalAcceptance::query()->count());
    }

    public function test_free_apartment_opening_stores_resident_data_acceptance_without_a_sale(): void
    {
        $user = User::factory()->create();
        $payload = [
            'name' => 'Ücretsiz Blok',
            'address' => 'Adres',
            'unit_count' => 12,
            'account_opening_date' => now()->toDateString(),
        ];

        $this->actingAs($user)
            ->from(route('subscriber.apartments.create'))
            ->post(route('subscriber.apartments.store'), $payload)
            ->assertRedirect(route('subscriber.apartments.create'))
            ->assertSessionHasErrors(['accept_resident_data', 'accept_privacy']);

        $this->assertDatabaseCount('apartments', 0);

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $payload + ['accept_resident_data' => '1'])
            ->assertSessionHasErrors('accept_privacy');

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $payload + ['accept_resident_data' => '1', 'accept_privacy' => '1'])
            ->assertRedirect();

        $apartment = Apartment::where('name', 'Ücretsiz Blok')->firstOrFail();
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'document_key' => LegalConsent::RESIDENT_DATA,
            'document_version' => '1',
            'user_subscription_id' => null,
        ]);
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'document_key' => LegalConsent::PRIVACY,
            'document_version' => '1',
            'user_subscription_id' => null,
        ]);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertSame(0, LegalAcceptance::query()->where('document_key', LegalConsent::DISTANCE_SALES)->count());
    }

    public function test_apartment_opening_ignores_paid_choice_and_does_not_store_a_sale(): void
    {
        $user = User::factory()->create();
        $payload = [
            'name' => 'Ücretli Blok',
            'address' => 'Adres',
            'unit_count' => 20,
            'account_opening_date' => now()->toDateString(),
            'wants_paid' => '1',
            'accept_sales' => '1',
            'accept_resident_data' => '1',
            'accept_privacy' => '1',
        ];

        $this->actingAs($user)
            ->post(route('subscriber.apartments.store'), $payload)
            ->assertRedirect();

        $apartment = Apartment::where('name', 'Ücretli Blok')->firstOrFail();
        $this->assertSame('free', $apartment->billing_plan);
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->assertSame(0, LegalAcceptance::query()->whereIn('document_key', [LegalConsent::DISTANCE_SALES, LegalConsent::PRE_INFORMATION])->count());
        $this->assertDatabaseHas('legal_acceptances', [
            'apartment_id' => $apartment->id,
            'document_key' => LegalConsent::RESIDENT_DATA,
        ]);
    }

    public function test_each_later_paid_order_stores_its_own_acceptance(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'Yenileme Apartmanı',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);

        $payload = [
            'apartment_ids' => [$apartment->id],
            'period' => 'monthly',
            'payment_method' => 'havale',
        ] + $this->billingSelection($user);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $payload)
            ->assertSessionHasErrors('accept_sales');

        $this->assertDatabaseCount('user_subscriptions', 0);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $payload + ['accept_sales' => '1'])
            ->assertRedirect();

        $order = UserSubscription::query()->firstOrFail();
        $this->assertSame(2, LegalAcceptance::query()->where('user_subscription_id', $order->id)->count());
        $this->assertSame(0, LegalAcceptance::query()->where('document_key', LegalConsent::MEMBERSHIP)->count());
    }

    public function test_membership_page_is_a_public_placeholder(): void
    {
        $this->get(route('legal.membership'))
            ->assertOk()
            ->assertSee('Üyelik Sözleşmesi')
            ->assertSee('Bu sayfa için içerik hazırlanmaktadır.');

        $this->get(route('legal.resident-data'))
            ->assertOk()
            ->assertSee('Daire Sakini Verisi')
            ->assertSee('Bu sayfa için içerik hazırlanmaktadır.');
    }

    private function challenge(): array
    {
        return [
            'register_challenge' => [
                'a' => 2,
                'b' => 3,
                'sum' => 5,
                'opened_at' => now()->subSeconds(10)->timestamp,
            ],
        ];
    }
}
