<?php

namespace Tests\Feature\Subscription;

use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\UserSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_subscription_gets_a_stable_number_and_uuid_without_a_payer(): void
    {
        $apartment = Apartment::factory()->create();

        $first = Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $second = Subscription::create([
            'apartment_id' => $apartment->id,
        ]);
        $custom = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-999999',
        ]);

        $year = now()->format('y');
        $this->assertSame("ABN-{$year}-000001", $first->subscription_no);
        $this->assertSame("ABN-{$year}-000002", $second->subscription_no);
        $this->assertSame('ABN-26-999999', $custom->subscription_no);
        $this->assertNotSame($first->uuid, $second->uuid);
        $this->assertNotNull($first->uuid);
        $this->assertFalse(Schema::hasColumn('subscriptions', 'user_id'));

        UserSubscription::factory()->create();
        $this->assertSame(3, Subscription::query()->count());
    }

    public function test_subscription_number_is_unique(): void
    {
        $apartment = Apartment::factory()->create();
        $existing = Subscription::create(['apartment_id' => $apartment->id]);

        $this->expectException(QueryException::class);

        Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => $existing->subscription_no,
        ]);
    }

    public function test_orders_and_periods_belong_to_the_subscription_without_replacing_the_order_link(): void
    {
        $apartment = Apartment::factory()->create();
        $subscription = Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
        $order = UserSubscription::factory()->create([
            'subscription_id' => $subscription->id,
            'order_number' => 'SIP-26-ABC123',
        ]);
        $item = SubscriptionItem::create([
            'apartment_subscription_id' => $subscription->id,
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $subscription->refresh();

        $this->assertTrue($subscription->apartment->is($apartment));
        $this->assertTrue($subscription->items()->first()->is($item));
        $this->assertTrue($subscription->userSubscriptions()->first()->is($order));
        $this->assertTrue($item->apartmentSubscription->is($subscription));
        $this->assertInstanceOf(UserSubscription::class, $item->subscription);
        $this->assertTrue($item->subscription->is($order));
        $this->assertTrue($order->subscription->is($subscription));
        $this->assertSame('SIP-26-ABC123', $order->order_number);
        $this->assertSame($subscription->subscription_no, $order->fresh()->subscription->subscription_no);
    }
}
