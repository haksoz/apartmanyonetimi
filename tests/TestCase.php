<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function billingSelection(\App\Models\User $user, array $overrides = []): array
    {
        $profile = \App\Models\BillingProfile::query()->create(array_merge([
            'user_id' => $user->id,
            'label' => 'Test profili',
            'party_type' => \App\Models\BillingProfile::TYPE_INDIVIDUAL,
            'legal_name' => $user->name,
            'is_active' => true,
        ], $overrides));

        return [
            'billing_mode' => 'existing',
            'billing_profile_id' => $profile->id,
        ];
    }

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
