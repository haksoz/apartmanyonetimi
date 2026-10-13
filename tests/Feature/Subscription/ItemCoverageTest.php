<?php

namespace Tests\Feature\Subscription;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_features_follow_the_subscription_item(): void
    {
        $user = User::factory()->create();

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => null,
            'amount' => 0,
        ], true);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'amount' => 300,
        ], false);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'amount' => 0,
        ], false);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->addMonth(),
            'expires_at' => now()->addMonths(2),
            'amount' => 300,
        ], true);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(),
            'amount' => 300,
        ], true);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'amount' => 300,
        ], true);

        $this->assertAccess($user, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'amount' => 300,
        ], true);

        $apartment = $this->apartment($user);
        $this->period($user, $apartment, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'amount' => 300,
        ], [
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);
        $this->assertFalse(FeatureGate::allows($apartment, 'auto_dues', $user));
    }

    private function assertAccess(User $user, array $item, bool $closed): void
    {
        $apartment = $this->apartment($user);
        $this->period($user, $apartment, $item);

        $this->assertSame(! $closed, FeatureGate::allows($apartment, 'auto_dues', $user));
    }

    private function apartment(User $user): Apartment
    {
        return Apartment::factory()->forUser($user)->create([
            'billing_plan' => 'free',
            'unit_count' => 10,
        ]);
    }

    private function period(User $user, Apartment $apartment, array $item, array $header = []): void
    {
        $subscription = UserSubscription::factory()->create(array_merge([
            'user_id' => $user->id,
            'package_id' => Package::factory()->create()->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subYear(),
            'expires_at' => now()->subMonth(),
        ], $header));

        SubscriptionItem::create(array_merge([
            'subscription_id' => $subscription->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => $apartment->unit_count,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'currency' => 'TRY',
        ], $item));
    }
}
