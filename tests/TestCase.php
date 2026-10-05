<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function grantPaidFeatures(\App\Models\Apartment $apartment): void
    {
        $apartment->update(['billing_plan' => 'paid']);

        $subscription = \App\Models\UserSubscription::factory()->create([
            'user_id' => $apartment->user_id,
            'is_active' => true,
            'status' => \App\Models\UserSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        \App\Models\SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => $apartment->unit_count,
            'band_label' => 'Test',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => \App\Models\SubscriptionItem::PLAN_PAID,
            'status' => \App\Models\SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
    }
}
