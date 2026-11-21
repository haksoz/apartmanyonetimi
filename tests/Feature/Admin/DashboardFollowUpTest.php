<?php

namespace Tests\Feature\Admin;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_uses_item_dates_and_excludes_renewals_and_unpaid_periods(): void
    {
        $this->freezeTime();
        $soon = $this->period(3);
        $this->period(7);
        $this->period(20);
        $this->period(7, period: UserSubscription::PERIOD_YEARLY);
        $this->period(8, period: UserSubscription::PERIOD_YEARLY);
        $this->period(30, period: UserSubscription::PERIOD_YEARLY);
        $this->period(31, period: UserSubscription::PERIOD_YEARLY);
        $this->period(-6);
        $this->period(-7);
        $this->period(-29);
        $this->period(-30);
        $dormant = $this->period(-40);
        $renewed = $this->period(-50);
        $this->period(60, $renewed);
        $this->period(4, null, UserSubscription::STATUS_PENDING);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk()->assertViewHas('followUpCounts', fn ($counts) => $counts->all() === [
            'monthly_3' => 1, 'monthly_7' => 2, 'yearly_7' => 1, 'yearly_30' => 2,
            'expired_7' => 1, 'expired_30' => 2, 'dormant' => 2,
        ])->assertSee($soon->subscription_no)->assertDontSee($renewed->subscription_no);

        $this->get(route('admin.dashboard', ['follow_up' => 'dormant']))
            ->assertOk()->assertSee($dormant->subscription_no)->assertDontSee($soon->subscription_no);
    }

    public function test_pending_order_summary_excludes_abandoned_and_deleted_orders(): void
    {
        $this->freezeTime();
        $pending = ['status' => UserSubscription::STATUS_PENDING, 'is_active' => false, 'price' => 250];
        $old = UserSubscription::factory()->create($pending + ['created_at' => now()->subDays(3)]);
        UserSubscription::factory()->create($pending);
        UserSubscription::factory()->create($pending + ['checkout_abandoned_at' => now()]);
        UserSubscription::factory()->create($pending)->delete();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('pendingOrderCount', 2)
            ->assertViewHas('staleOrderCount', 1)
            ->assertViewHas('pendingOrderAmount', fn ($amount) => (float) $amount === 500.0)
            ->assertSee(route('admin.orders.show', $old), false);
    }

    public function test_changed_billing_period_is_classified_by_latest_paid_expiry(): void
    {
        $this->freezeTime();
        $subscription = $this->period(1);
        $this->period(5, $subscription, period: UserSubscription::PERIOD_YEARLY);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('followUpCounts', fn ($counts) =>
                $counts['monthly_3'] === 0 && $counts['monthly_7'] === 0 && $counts['yearly_7'] === 1);
    }

    private function period(int $days, ?Subscription $subscription = null, string $status = UserSubscription::STATUS_ACTIVE, string $period = UserSubscription::PERIOD_MONTHLY): Subscription
    {
        $subscription ??= Subscription::create(['apartment_name' => 'Takip Apartmanı']);
        $order = UserSubscription::factory()->create(['status' => $status, 'period' => $period]);
        $subscription->items()->create([
            'subscription_id' => $order->id,
            'apartment_name' => 'Takip Apartmanı', 'unit_count' => 10,
            'band_label' => '1–14 daire', 'band_min_units' => 1,
            'amount' => 250, 'currency' => 'TRY', 'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDays(90), 'expires_at' => now()->addDays($days),
        ]);

        return $subscription;
    }
}
