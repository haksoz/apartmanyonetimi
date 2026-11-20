<?php

namespace Tests\Feature\Admin;

use App\Models\SubscriptionPaymentAttempt;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(array $orderAttributes = [], array $attributes = []): SubscriptionPaymentAttempt
    {
        $order = UserSubscription::factory()->create($orderAttributes + ['payment_method' => 'kredi_kartı', 'status' => 'pending', 'is_active' => false]);

        return $order->paymentAttempts()->create($attributes + ['provider' => 'paytr', 'merchant_oid' => 'AC'.bin2hex(random_bytes(12)), 'amount_minor' => 12000, 'currency' => 'TRY', 'is_test' => true, 'status' => 'pending']);
    }

    public function test_abandoned_orders_are_separated_without_deleting_payment_records(): void
    {
        $attempt = $this->attempt(['checkout_abandoned_at' => now(), 'status' => 'cancelled'], ['status' => 'cancelled']);
        $order = $attempt->subscription;
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.orders.index'))->assertOk()->assertDontSee($order->order_number);
        $this->get(route('admin.orders.index', ['status' => 'abandoned']))->assertOk()->assertSee($order->order_number);
        $this->get(route('admin.card-transactions.index'))->assertOk()->assertSee($attempt->merchant_oid)->assertSee($order->order_number);
        $this->assertNotNull($order->fresh());
        $this->assertNotNull($attempt->fresh());
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('orderCount', 0)->assertViewHas('cancelledOrderCount', 0);
    }

    public function test_review_and_successful_orders_remain_visible_after_checkout_exit(): void
    {
        $review = $this->attempt(['checkout_abandoned_at' => now()], ['status' => 'unknown', 'review_required' => true]);
        $success = $this->attempt(['checkout_abandoned_at' => now(), 'status' => 'active'], ['status' => 'succeeded']);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.orders.index'))->assertOk()
            ->assertSee($review->subscription->order_number)->assertSee($success->subscription->order_number);
    }

    public function test_filters_and_deleted_order_history_are_available_without_exposing_tokens(): void
    {
        $attempt = $this->attempt(['checkout_abandoned_at' => now()], ['status' => 'failed', 'iframe_token' => 'PRIVATE-TOKEN', 'payer_snapshot' => ['payer_name' => 'PRIVATE-PAYER']]);
        $attempt->notifications()->create(['fingerprint' => hash('sha256', 'notification'), 'provider_status' => 'failed', 'total_amount_minor' => 12000, 'reported_test_mode' => true, 'failed_reason_code' => '6', 'outcome' => 'failed', 'deliveries' => 2]);
        $other = $this->attempt([], ['is_test' => false]);
        $bank = $this->attempt([], ['provider' => 'bank_transfer']);
        $attempt->subscription->delete();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.card-transactions.index', ['status' => 'failed', 'mode' => 'test', 'search' => $attempt->subscription->order_number]))
            ->assertOk()->assertSee($attempt->merchant_oid)->assertDontSee($other->merchant_oid)->assertDontSee($bank->merchant_oid);
        $this->get(route('admin.card-transactions.show', $attempt))->assertOk()->assertSee('Bildirim geçmişi')
            ->assertSee('Pasif silinen')->assertSee('Başarısız ödeme')->assertSee('Teslim sayısı: 2')
            ->assertDontSee('PRIVATE-TOKEN')->assertDontSee('PRIVATE-PAYER');
        $this->get(route('admin.card-transactions.show', $bank))->assertNotFound();
    }

    public function test_non_admin_cannot_read_payment_logs(): void
    {
        $attempt = $this->attempt();
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.card-transactions.index'))->assertForbidden();
        $this->get(route('admin.card-transactions.show', $attempt))->assertForbidden();
    }
}
