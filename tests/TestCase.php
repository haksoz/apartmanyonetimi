<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function saveAssessmentPlan(string $url, array $payload, string $method = 'post'): \Illuminate\Testing\TestResponse
    {
        $preview = $this->{$method}($url, $payload);
        if ($preview->status() !== 200) {
            return $preview;
        }
        $decisions = ['include_past_installments' => $payload['include_past_installments'] ?? 0];
        if ($preview->viewData('summary')['current_eligible']) {
            $decisions['current_month_action'] = $payload['current_month_action'] ?? 'skip';
        }
        $preview = $this->post(route('assessment-plans.confirm'), [
            ...$decisions, 'confirmation_token' => $preview->viewData('token'), 'update_summary' => 1,
        ]);

        return $this->post(route('assessment-plans.confirm'), [...$decisions, 'confirmation_token' => $preview->viewData('token')]);
    }

    protected function saveAssessmentPlanUpdate(\App\Models\AssessmentPlan $plan, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->saveAssessmentPlan(route('assessment-plans.update', $plan), $payload, 'put');
    }

    protected function saveExpensePlan(string $url, array $payload, string $method = 'post'): \Illuminate\Testing\TestResponse
    {
        $preview = $this->{$method}($url, $payload);
        if ($preview->status() !== 200) {
            return $preview;
        }
        $decisions = ['include_past_periods' => $payload['include_past_periods'] ?? 0];
        if ($preview->viewData('summary')['current_eligible']) {
            $decisions['current_month_action'] = 'skip';
        }
        $preview = $this->post(route('expense-plans.confirm'), [
            ...$decisions, 'confirmation_token' => $preview->viewData('token'), 'update_summary' => 1,
        ]);

        return $this->post(route('expense-plans.confirm'), [...$decisions, 'confirmation_token' => $preview->viewData('token')]);
    }

    protected function saveExpensePlanUpdate(\App\Models\ExpensePlan $plan, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->saveExpensePlan(route('expense-plans.update', $plan), $payload, 'put');
    }

    protected function saveAidatPlan(array $payload): \Illuminate\Testing\TestResponse
    {
        $preview = $this->post(route('due-plans.store'), $payload);
        if ($preview->status() !== 200) {
            return $preview;
        }
        if ($preview->viewData('summary')['current']['eligible'] && $preview->viewData('summary')['current']['action'] === null) {
            $preview = $this->post(route('due-plans.store'), [
                'confirmation_token' => $preview->viewData('token'), 'current_month_action' => 'skip',
            ]);
        }

        return $this->post(route('due-plans.store'), ['confirmation_token' => $preview->viewData('token')]);
    }

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
