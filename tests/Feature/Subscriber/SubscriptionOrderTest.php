<?php

namespace Tests\Feature\Subscriber;

use App\Models\BankAccount;
use App\Models\Package;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SubscriptionOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_payment_card_not_shown_when_subscription_has_more_than_three_days_left(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();

        $manager->subscription->update([
            'expires_at' => now()->addDays(10),
            'price' => 100,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertDontSeeText('Yaklaşan Ödeme');
    }

    public function test_upcoming_payment_card_shown_three_days_before_expiry(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();

        $expiry = now()->addDays(2);
        $manager->subscription->update([
            'expires_at' => $expiry,
            'price' => 100,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSeeText('Yaklaşan Ödeme')
            ->assertSeeText($expiry->format('d.m.Y'));
    }

    public function test_upcoming_payment_card_changes_title_after_expiration(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();

        $expiredSubscription = $manager->subscription;
        $expiredSubscription->update([
            'expires_at' => now()->subDay(),
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
            'ended_at' => now(),
            'price' => 100,
        ]);

        // Create new active subscription so user has one
        UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 100,
            'is_active' => true,
            'status' => UserSubscription::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSeeText('Aboneliğiniz Sona Erdi')
            ->assertSeeText('Kullanmaya devam etmek için ödeme yapın');
    }

    public function test_upgrade_page_lists_only_higher_packages(): void
    {
        $currentPackage = Package::factory()->create([
            'monthly_price' => 100,
            'sort_order' => 1,
            'show_on_website' => true,
        ]);
        $higherPackage = Package::factory()->create([
            'monthly_price' => 200,
            'sort_order' => 2,
            'show_on_website' => true,
        ]);
        $lowerPackage = Package::factory()->create([
            'monthly_price' => 50,
            'sort_order' => 0,
            'show_on_website' => true,
        ]);

        $manager = User::factory()->withSubscription($currentPackage, 'monthly')->create();

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.create', ['type' => 'upgrade']))
            ->assertOk()
            ->assertSee($higherPackage->name)
            ->assertDontSee($lowerPackage->name);
    }

    public function test_subscriber_can_create_havale_order_without_reference(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.store'), [
                'package_id' => $package->id,
                'period' => 'yearly',
                'payment_method' => 'havale',
            ])
            ->assertRedirect();

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->order_number);
        $this->assertStringStartsWith('SIP-', $pending->order_number);
        $this->assertEquals($package->id, $pending->package_id);
        $this->assertEquals('yearly', $pending->period);
        $this->assertEquals(1000, $pending->price);
        $this->assertNull($pending->receipt_reference);
        $this->assertEquals('havale', $pending->payment_method);
        $this->assertFalse($pending->is_active);

        // Active subscription should remain unchanged
        $this->assertTrue($manager->fresh()->subscription->is_active);
    }

    public function test_subscriber_can_create_credit_card_pending_order(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.store'), [
                'package_id' => $package->id,
                'period' => 'monthly',
                'payment_method' => 'kredi_kartı',
            ])
            ->assertRedirect();

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->order_number);
        $this->assertEquals('kredi_kartı', $pending->payment_method);
        $this->assertEquals(100, $pending->price);
        $this->assertFalse($pending->is_active);
    }

    public function test_receipt_page_shows_active_bank_accounts(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
        ]);
        $account = BankAccount::factory()->create(['is_active' => true]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee($account->name)
            ->assertSee($account->iban)
            ->assertSee($subscription->order_number)
            ->assertSeeText('Havale/EFT açıklama kısmına');
    }

    public function test_subscriber_can_add_payment_info(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'reference_code' => 'REF-987654',
            ])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertEquals('REF-987654', $subscription->receipt_reference);

        $file = UploadedFile::fake()->image('receipt.png');

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'receipt' => $file,
            ])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertNotNull($subscription->receipt_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($subscription->receipt_path));
    }

    public function test_subscriber_can_view_own_orders_list(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $order = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk()
            ->assertSee($package->name)
            ->assertSee('Bekliyor');
    }

    public function test_subscriber_cannot_view_other_users_receipt(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $other = User::factory()->withSubscription($package)->create();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $other->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertForbidden();
    }
}
