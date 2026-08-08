<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerSubscriptionOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_current_subscription_features_and_limits(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.update', $manager), [
                'feature_auto_dues' => '1',
                'feature_user_portal' => '0',
                'feature_reports' => '1',
                'feature_multi_apartment' => '0',
                'multi_apartment_limit_override' => 5,
                'max_apartments' => 10,
                'notes' => 'Özel not',
            ])
            ->assertRedirect();

        $subscription = $manager->fresh()->subscription;
        $this->assertTrue($subscription->feature_auto_dues);
        $this->assertFalse($subscription->feature_user_portal);
        $this->assertTrue($subscription->feature_reports);
        $this->assertFalse($subscription->feature_multi_apartment);
        $this->assertEquals(5, $subscription->multi_apartment_limit_override);
        $this->assertEquals(10, $manager->fresh()->quotaOverride->max_apartments);
        $this->assertEquals('Özel not', $subscription->notes);
    }

    public function test_admin_can_create_paid_order_and_activate_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150, 'yearly_price' => 1500]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'yearly',
                    'price' => 1200,
                ],
                'is_paid' => '1',
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'havale',
                'reference_code' => 'REF123',
                'notes' => 'Yıllık ödeme alındı',
            ])
            ->assertRedirect();

        $subscription = $manager->fresh()->subscription;
        $this->assertNotNull($subscription);
        $this->assertEquals($package->id, $subscription->package_id);
        $this->assertEquals('yearly', $subscription->period);
        $this->assertEquals(1200, $subscription->price);
        $this->assertTrue($subscription->is_active);
        $this->assertEquals(UserSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertEquals(1, $subscription->payments()->count());
        $this->assertEquals(1200, $subscription->payments()->first()->amount);
    }

    public function test_admin_can_create_unpaid_order_as_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ])
            ->assertRedirect();

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $this->assertNotNull($pending);
        $this->assertEquals($package->id, $pending->package_id);
        $this->assertFalse($pending->is_active);
        $this->assertEquals(UserSubscription::STATUS_PENDING, $pending->status);

        $active = $manager->fresh()->subscription;
        $this->assertNotEquals($pending->id, $active->id);
    }

    public function test_admin_can_approve_pending_order(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'havale',
                'reference_code' => 'DEKONT123',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertTrue($pending->is_active);
        $this->assertEquals(UserSubscription::STATUS_ACTIVE, $pending->status);
        $this->assertNotNull($pending->started_at);
        $this->assertNotNull($pending->expires_at);
        $this->assertEquals(1, $pending->payments()->count());
        $this->assertEquals('havale', $pending->payments()->first()->payment_method);
        $this->assertEquals('DEKONT123', $pending->payments()->first()->reference_code);
    }

    public function test_creating_paid_order_closes_previous_active_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $oldSubscription = $manager->subscription;
        $package = Package::factory()->create(['monthly_price' => 200]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 200,
                ],
                'is_paid' => '1',
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'nakit',
            ]);

        $oldSubscription->refresh();
        $this->assertFalse($oldSubscription->is_active);
        $this->assertNotNull($oldSubscription->ended_at);
        $this->assertEquals(UserSubscription::STATUS_CANCELLED, $oldSubscription->status);
    }

    public function test_cash_approval_generates_receipt_number(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'nakit',
            ]);

        $payment = $pending->fresh()->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals('nakit', $payment->payment_method);
        $this->assertNotNull($payment->reference_code);
        $this->assertStringStartsWith('NKT-', $payment->reference_code);
    }

    public function test_havale_approval_stores_reference_code(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-2026-001',
            ]);

        $payment = $pending->fresh()->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals('havale', $payment->payment_method);
        $this->assertEquals('HVL-2026-001', $payment->reference_code);
    }

    public function test_approval_uses_existing_receipt_reference_when_not_provided(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $pending->update(['receipt_reference' => 'USR-DEKONT-123']);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'havale',
            ]);

        $payment = $pending->fresh()->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals('havale', $payment->payment_method);
        $this->assertEquals('USR-DEKONT-123', $payment->reference_code);
    }

    public function test_show_page_displays_approval_icon_when_receipt_submitted(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $pending->update(['receipt_reference' => 'DEKONT-ABC']);

        $this->actingAs($admin)
            ->get(route('admin.managers.show', $manager))
            ->assertOk()
            ->assertSeeText('Onay Bekliyor')
            ->assertSeeText('DEKONT-ABC');
    }

    public function test_approved_order_inherits_package_features(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['multi_apartment_limit' => 5]);

        PackageFeature::create(['package_id' => $package->id, 'feature_key' => 'Otomatik aidat planlama', 'is_enabled' => true]);
        PackageFeature::create(['package_id' => $package->id, 'feature_key' => 'Kullanıcı portalı erişimi', 'is_enabled' => true]);
        PackageFeature::create(['package_id' => $package->id, 'feature_key' => 'Hesap ekstresi ve raporlar', 'is_enabled' => false]);
        PackageFeature::create(['package_id' => $package->id, 'feature_key' => 'Çoklu apartman yönetimi', 'is_enabled' => true]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();
        $this->assertTrue($pending->feature_auto_dues);
        $this->assertTrue($pending->feature_user_portal);
        $this->assertFalse($pending->feature_reports);
        $this->assertTrue($pending->feature_multi_apartment);
        $this->assertEquals(5, $pending->multi_apartment_limit_override);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $pending]), [
                'payment_method' => 'havale',
                'reference_code' => 'DEKONT-XYZ',
            ]);

        $active = $manager->fresh()->subscription;
        $this->assertNotNull($active);
        $this->assertEquals($package->id, $active->package_id);
        $this->assertTrue($active->feature_auto_dues);
        $this->assertTrue($active->feature_user_portal);
        $this->assertFalse($active->feature_reports);
        $this->assertTrue($active->feature_multi_apartment);
        $this->assertEquals(5, $active->multi_apartment_limit_override);

        $this->actingAs($admin)
            ->get(route('admin.managers.show', $manager))
            ->assertStatus(200)
            ->assertSee('checked', false);
    }

    public function test_admin_can_reject_pending_order(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        $this->actingAs($admin)
            ->post(route('admin.managers.subscription.order', $manager), [
                'order' => [
                    'package_id' => $package->id,
                    'period' => 'monthly',
                    'price' => 150,
                ],
                'is_paid' => '0',
            ]);

        $pending = $manager->fresh()->subscriptions()->pending()->first();

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.reject', [$manager, $pending]), [
                'rejection_notes' => 'Kart hatası nedeniyle reddedildi.',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertFalse($pending->is_active);
        $this->assertEquals(UserSubscription::STATUS_CANCELLED, $pending->status);
        $this->assertNotNull($pending->ended_at);
        $this->assertEquals('Kart hatası nedeniyle reddedildi.', $pending->notes);
    }

    public function test_admin_index_shows_pending_order_icon(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();
        $package = Package::factory()->create(['monthly_price' => 150]);

        UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 150,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertSee($manager->name)
            ->assertSee('Bekleyen sipariş var');
    }
}
