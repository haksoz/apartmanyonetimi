<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPaymentAttempt;
use App\Support\Paytr\Access;
use App\Support\Paytr\VerifiedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaytrTestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['registration.enabled' => false, 'payments.card_enabled' => true, 'paytr.test_mode' => true,
            'paytr.allow_test_settlement' => true, 'paytr.test_accounts_only' => true,
            'paytr.test_account_emails' => ['aidatcep01@ko.com.tr', 'aidatcep02@ko.com.tr']]);
    }

    public function test_signup_is_closed_on_get_and_post_and_public_links_are_hidden(): void
    {
        $this->get(route('register'))->assertForbidden()->assertSee('Yeni üyelikler geçici olarak kapalı');
        $this->get(route('register', ['package' => 'anything']))->assertForbidden();
        $this->post(route('register'), ['email' => 'new@example.com'])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('landing'))->assertOk()->assertDontSee('href="'.route('register').'"', false);
        $this->get(route('pricing'))->assertOk()->assertDontSee('href="'.route('register').'"', false);
        $this->get(route('login'))->assertOk()->assertDontSee('Hesabınız yok mu?');
    }

    public function test_production_test_access_requires_an_active_listed_account_and_closed_signup(): void
    {
        app()->instance('env', 'production');
        $access = app(Access::class);
        $first = User::factory()->create(['email' => 'aidatcep01@ko.com.tr']);
        $second = User::factory()->create(['email' => 'aidatcep02@ko.com.tr']);
        $other = User::factory()->create();
        $this->assertTrue($access->canStart($first));
        $this->assertTrue($access->canSettleTest($first));
        $this->assertTrue($access->canStart($second));
        $this->assertFalse($access->canStart($other));
        $this->assertFalse($access->canSettleTest($other));
        $this->assertFalse($access->canStart(null));
        $first->update(['deactivated_at' => now()]);
        $this->assertFalse($access->canStart($first));
        config(['registration.enabled' => true]);
        $this->assertFalse($access->canStart($second));
        $this->assertFalse($access->canSettleTest($second));
    }

    public function test_configuration_cannot_accidentally_open_live_payment_in_test_account_mode(): void
    {
        $user = User::factory()->create(['email' => 'aidatcep01@ko.com.tr']);
        config(['paytr.test_mode' => false]);
        $this->assertFalse(app(Access::class)->canStart($user));
        config(['paytr.test_mode' => true, 'paytr.test_accounts_only' => false]);
        app()->instance('env', 'production');
        $this->assertFalse(app(Access::class)->canStart($user));
        $this->assertFalse(app(Access::class)->canSettleTest($user));
    }
}
