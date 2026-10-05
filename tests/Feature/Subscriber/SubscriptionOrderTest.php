<?php

namespace Tests\Feature\Subscriber;

use App\Models\Apartment;
use App\Models\BankAccount;
use App\Models\Package;
use App\Models\SubscriptionItem;
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

    public function test_apartment_card_shows_renewal_when_coverage_ends_within_three_days(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();
        $expiry = now()->addDays(2);
        $manager->subscription->update([
            'expires_at' => $expiry,
            'price' => 150,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Süreli Apartman',
            'unit_count' => 10,
            'billing_plan' => 'paid',
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        SubscriptionItem::create([
            'subscription_id' => $manager->subscription->id,
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
            'expires_at' => $expiry,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Süreli Apartman')
            ->assertSee('Ücretli')
            ->assertSee('Yenileme yaklaşıyor')
            ->assertSee($expiry->format('d.m.Y'));
    }

    public function test_expired_coverage_leaves_the_apartment_on_the_free_card(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();
        $manager->subscription->update([
            'expires_at' => now()->subDay(),
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Süresi Bitmiş Apartman',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Süresi Bitmiş Apartman')
            ->assertSee('Ücretsiz')
            ->assertSee('Ücretliye geç')
            ->assertDontSee('Aboneliğiniz Sona Erdi');
    }

    public function test_order_page_lists_the_managers_apartments(): void
    {
        $package = Package::factory()->create();
        $manager = User::factory()->withSubscription($package, 'monthly')->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Sipariş Apartmanı',
            'unit_count' => 10,
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Sipariş Apartmanı')
            ->assertDontSee('Başlangıç');
    }

    public function test_subscriber_can_create_havale_order_without_reference(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Havale Apartmanı',
            'unit_count' => 10,
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        \App\Models\Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
            ])
            ->assertRedirect();

        $pending = $manager->fresh()->subscriptions()->pending()->with('items')->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->order_number);
        $this->assertStringStartsWith('SIP-', $pending->order_number);
        $this->assertEquals('yearly', $pending->period);
        $this->assertEquals(1500, (float) $pending->price);
        $this->assertNull($pending->receipt_reference);
        $this->assertEquals('havale', $pending->payment_method);
        $this->assertFalse($pending->is_active);
        $this->assertSame('Havale Apartmanı', $pending->items->first()->apartment_name);
        $this->assertEquals(1500, (float) $pending->items->first()->amount);

        $this->assertTrue($manager->fresh()->subscription->is_active);
        $this->assertNotEquals($pending->id, $manager->fresh()->subscription->id);
    }

    public function test_subscriber_can_create_credit_card_pending_order(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100, 'yearly_price' => 1000]);
        $manager = User::factory()->withSubscription($package, 'monthly')->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'unit_count' => 10,
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        \App\Models\Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'monthly',
                'payment_method' => 'kredi_kartı',
            ])
            ->assertRedirect();

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $this->assertNotNull($pending);
        $this->assertNotNull($pending->order_number);
        $this->assertEquals('kredi_kartı', $pending->payment_method);
        $this->assertEquals(150, (float) $pending->price);
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
            ->assertSee('Eski paket kaydı')
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
