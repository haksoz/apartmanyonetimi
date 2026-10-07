<?php

namespace Tests\Feature\Subscriber;

use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleApartmentOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lets_the_customer_choose_only_one_apartment(): void
    {
        [$user, $first] = $this->freeApartment('Sarıkayalar');
        [, $second] = $this->freeApartment('Dertli Deliler', $user);

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.create', ['type' => 'renew']))
            ->assertOk()
            ->assertSee('Sarıkayalar')
            ->assertSee('Dertli Deliler')
            ->assertSee('Temel kullanım')
            ->assertSee('Ücretli Pakete Geç')
            ->assertSee('name="apartment_ids[]"', false)
            ->assertSee('type="radio"', false)
            ->assertDontSee('type="checkbox"', false);

        $this->assertNotSame($first->id, $second->id);
    }

    public function test_store_rejects_more_than_one_apartment_in_a_single_order(): void
    {
        [$user, $first] = $this->freeApartment('Sarıkayalar');
        [, $second] = $this->freeApartment('Dertli Deliler', $user);

        $this->actingAs($user)
            ->from(route('subscriber.subscriptions.create'))
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$first->id, $second->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
            ])
            ->assertRedirect(route('subscriber.subscriptions.create'))
            ->assertSessionHasErrors('apartment_ids');

        $this->assertSame(0, UserSubscription::query()->count());
    }

    public function test_store_rejects_an_apartment_the_customer_does_not_belong_to(): void
    {
        [$user] = $this->freeApartment('Sarıkayalar');
        [, $other] = $this->freeApartment('Yabancı Apartman');

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$other->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
            ])
            ->assertSessionHasErrors('apartment_ids');

        $this->assertSame(0, UserSubscription::query()->count());
    }

    public function test_a_free_apartment_can_start_a_paid_order_without_a_free_subscription_order(): void
    {
        [$user, $apartment, $free] = $this->freeApartment('Sarıkayalar');

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $this->orderPayload($apartment))
            ->assertRedirect();

        $order = UserSubscription::query()->firstOrFail();
        $paid = $order->items()->firstOrFail();

        $this->assertSame(1, UserSubscription::query()->count());
        $this->assertNull($free->fresh()->subscription_id);
        $this->assertSame(SubscriptionItem::PLAN_FREE, $free->fresh()->plan);
        $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $paid->status);
        $this->assertSame($free->apartment_subscription_id, $paid->apartment_subscription_id);
        $this->assertSame(1, Subscription::query()->count());

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Sarıkayalar · 10 daire AidatCep Kullanım / Aylık')
            ->assertSee('Ödeme Gir')
            ->assertSee('md:hidden', false)
            ->assertDontSee('>Dönem<', false)
            ->assertDontSee('Ücretsiz kullanım');
    }

    public function test_renewal_is_appended_to_the_current_paid_period_without_a_new_subscription(): void
    {
        $this->travelTo('2026-10-05 10:00:00');

        [$user, $apartment, $free] = $this->freeApartment('Dertli Deliler');
        $record = $free->apartmentSubscription;
        $originalStart = $record->started_at->format('Y-m-d H:i:s');
        $admin = User::factory()->admin()->create();

        $first = $this->placeOrder($user, $apartment);
        $this->approve($admin, $user, $first);

        $current = $first->items()->firstOrFail()->fresh();
        $this->assertSame('2026-11-05 10:00:00', $current->expires_at->format('Y-m-d H:i:s'));

        $this->travelTo('2026-10-20 10:00:00');

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Yenile')
            ->assertSee('05.10.2026')
            ->assertSee('05.11.2026')
            ->assertSee($record->subscription_no);

        $renewal = $this->placeOrder($user, $apartment);
        $this->approve($admin, $user, $renewal);

        $renewed = $renewal->items()->firstOrFail()->fresh();
        $current->refresh();
        $record->refresh();

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame($record->id, $renewed->apartment_subscription_id);
        $this->assertSame('2026-11-05 10:00:00', $renewed->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-05 10:00:00', $renewed->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $current->status);
        $this->assertNull($current->ended_at);
        $this->assertSame('2026-10-05 10:00:00', $current->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 10:00:00', $current->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_an_expired_paid_apartment_can_be_purchased_again(): void
    {
        $this->travelTo('2026-10-05 10:00:00');

        [$user, $apartment] = $this->freeApartment('Dertli Deliler');
        $admin = User::factory()->admin()->create();
        $first = $this->placeOrder($user, $apartment);
        $this->approve($admin, $user, $first);

        $this->travelTo('2026-12-01 10:00:00');

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Yeniden Başlat');

        $again = $this->placeOrder($user, $apartment);
        $this->approve($admin, $user, $again);

        $item = $again->items()->firstOrFail()->fresh();
        $this->assertSame('2026-12-01 10:00:00', $item->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-01 10:00:00', $item->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Subscription::query()->count());
    }

    public function test_cancel_button_is_hidden_once_payment_proof_is_sent(): void
    {
        [$user, $apartment] = $this->freeApartment('Dertli Deliler');
        $pending = $this->placeOrder($user, $apartment);
        $pending->update(['receipt_reference' => 'REF-VAR']);

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Ödeme Bekleyen Siparişiniz Var')
            ->assertSee($pending->order_number)
            ->assertDontSee('Siparişi İptal Et');
    }

    public function test_a_pending_order_blocks_another_order_until_the_customer_cancels_it(): void
    {
        $this->travelTo('2026-10-05 10:00:00');

        [$user, $apartment] = $this->freeApartment('Dertli Deliler');
        $admin = User::factory()->admin()->create();
        $active = $this->placeOrder($user, $apartment);
        $this->approve($admin, $user, $active);
        $activeItem = $active->items()->firstOrFail()->fresh();
        $record = $activeItem->apartmentSubscription;
        $originalStart = $record->started_at->format('Y-m-d H:i:s');

        $pending = $this->placeOrder($user, $apartment);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $this->orderPayload($apartment))
            ->assertSessionHasErrors('apartment_ids');

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Ödeme Bekleyen Siparişiniz Var')
            ->assertSee($pending->order_number)
            ->assertSee('Ödemeye Devam Et')
            ->assertSee('Siparişi İptal Et')
            ->assertDontSee('name="apartment_ids[]" value="'.$apartment->id.'"', false);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.cancel', $pending))
            ->assertRedirect(route('subscriber.subscriptions.create', ['type' => 'renew']));

        $pending->refresh();
        $activeItem->refresh();
        $record->refresh();

        $this->assertSame(UserSubscription::STATUS_CANCELLED, $pending->status);
        $this->assertSame(SubscriptionItem::STATUS_CANCELLED, $pending->items()->firstOrFail()->status);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $activeItem->status);
        $this->assertSame('2026-10-05 10:00:00', $activeItem->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 10:00:00', $activeItem->expires_at->format('Y-m-d H:i:s'));
        $this->assertNull($activeItem->ended_at);
        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));

        $replacement = $this->placeOrder($user, $apartment);
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $replacement->items()->firstOrFail()->status);
    }

    public function test_orders_index_shows_paid_orders_and_hides_free_basic_use(): void
    {
        [$user, $apartment] = $this->freeApartment('Sarıkayalar');
        $legacy = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'SIP-26-UCRETSIZ',
            'price' => 0,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'notes' => 'Ücretsiz kullanım',
        ]);
        SubscriptionItem::create([
            'subscription_id' => $legacy->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 0,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $paid = $this->placeOrder($user, $apartment);

        $this->actingAs($user)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk()
            ->assertSee($paid->order_number)
            ->assertDontSee('SIP-26-UCRETSIZ');
    }

    public function test_each_apartment_gets_its_own_order(): void
    {
        [$user, $first] = $this->freeApartment('Sarıkayalar');
        [, $second] = $this->freeApartment('Dertli Deliler', $user);

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $this->orderPayload($first))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $this->orderPayload($second))
            ->assertRedirect();

        $orders = UserSubscription::query()->with('items')->get();
        $this->assertCount(2, $orders);
        $this->assertSame(
            [$first->id, $second->id],
            $orders->map(fn (UserSubscription $order) => $order->items->first()->apartment_id)->sort()->values()->all()
        );
        $orders->each(fn (UserSubscription $order) => $this->assertCount(1, $order->items));

        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$first->id, $second->id],
                'period' => 'monthly',
                'payment_method' => 'havale',
            ])
            ->assertSessionHasErrors('apartment_ids');

        $this->assertSame(2, UserSubscription::query()->count());
    }

    /**
     * @return array{0: User, 1: Apartment, 2: SubscriptionItem}
     */
    private function freeApartment(string $name, ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => $name,
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);

        $free = SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan', SubscriptionItem::PLAN_FREE)
            ->firstOrFail();

        return [$user, $apartment, $free];
    }

    private function orderPayload(Apartment $apartment): array
    {
        return [
            'apartment_ids' => [$apartment->id],
            'period' => 'monthly',
            'payment_method' => 'havale',
        ];
    }

    private function placeOrder(User $user, Apartment $apartment): UserSubscription
    {
        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), $this->orderPayload($apartment))
            ->assertRedirect();

        return UserSubscription::query()->where('status', UserSubscription::STATUS_PENDING)->latest('id')->firstOrFail();
    }

    private function approve(User $admin, User $user, UserSubscription $order): void
    {
        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-TEK',
            ])
            ->assertRedirect();
    }
}
