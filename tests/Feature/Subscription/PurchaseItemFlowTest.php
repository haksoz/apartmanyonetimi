<?php

namespace Tests\Feature\Subscription;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\FeatureGate;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PurchaseItemFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_paid_item_does_not_open_features_or_close_the_free_period(): void
    {
        [$user, $apartment, $free] = $this->freeApartment('A Blok');

        $order = $this->pendingOrder($user, [$apartment]);
        $paid = $order->items()->firstOrFail();

        $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $paid->status);
        $this->assertNull($paid->started_at);
        $this->assertNull($paid->expires_at);
        $this->assertNull($paid->ended_at);
        $this->assertFalse(FeatureGate::allows($apartment, 'auto_dues', $user));

        $free->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->ended_at);
        $this->assertNull($free->subscription_id);
        $this->assertSame(1, UserSubscription::query()->count());

        $record = $free->apartmentSubscription;
        $this->assertNotNull($record);
        $this->assertSame(1, \App\Models\Subscription::query()->count());
        $this->assertSame($record->id, $order->subscription_id);
        $this->assertSame($record->id, $paid->apartment_subscription_id);
        $this->assertSame($order->id, $paid->subscription_id);
        $this->assertSame($user->id, $order->user_id);
    }

    public function test_a_legacy_apartment_without_a_subscription_cannot_start_a_paid_order(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'Eski Apartman',
            'unit_count' => 10,
            'billing_plan' => 'paid',
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

        try {
            $this->pendingOrder($user, [$apartment]);
            $this->fail('Abonelik kaydı olmayan apartman sipariş açmamalı.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('apartment_ids', $exception->errors());
        }

        $this->assertSame(0, \App\Models\Subscription::query()->count());
        $this->assertSame(0, UserSubscription::query()->count());
        $this->assertSame(0, SubscriptionItem::query()->count());
    }

    public function test_approval_activates_paid_items_and_closes_only_the_ordered_apartments_free_periods(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        [, $apartmentA, $freeA] = $this->freeApartment('A Blok', $user);
        [, $apartmentB, $freeB] = $this->freeApartment('B Blok', $user);
        [, $apartmentC, $freeC] = $this->freeApartment('C Blok', $user);

        $legacy = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'package_id' => Package::factory()->create()->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $pendingElsewhere = $this->pendingOrder($user, [$apartmentC]);
        $order = $this->pendingOrder($user, [$apartmentA, $apartmentB]);
        $startedA = $freeA->apartmentSubscription->started_at->copy();
        $startedB = $freeB->apartmentSubscription->started_at->copy();
        $startedC = $freeC->apartmentSubscription->started_at->copy();
        $this->assertNull($order->subscription_id);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-AB',
            ])
            ->assertRedirect();

        $paidA = $order->items()->where('apartment_id', $apartmentA->id)->firstOrFail()->fresh();
        $paidB = $order->items()->where('apartment_id', $apartmentB->id)->firstOrFail()->fresh();

        foreach ([$paidA, $paidB] as $paid) {
            $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
            $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $paid->status);
            $this->assertNotNull($paid->started_at);
            $this->assertNotNull($paid->expires_at);
            $this->assertNull($paid->ended_at);
            $this->assertTrue($paid->started_at->lt($paid->expires_at));
        }

        $freeA->refresh();
        $freeB->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $freeA->status);
        $this->assertNull($freeA->ended_at);
        $this->assertNull($freeA->subscription_id);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $freeB->status);
        $this->assertNull($freeB->ended_at);
        $this->assertNull($freeB->subscription_id);

        $freeC->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $freeC->status);
        $this->assertNull($freeC->ended_at);

        $outsidePending = $pendingElsewhere->items()->firstOrFail()->fresh();
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $outsidePending->status);
        $this->assertNull($outsidePending->ended_at);

        $legacy->refresh();
        $this->assertTrue($legacy->is_active);
        $this->assertSame(UserSubscription::STATUS_ACTIVE, $legacy->status);
        $this->assertSame(0, $legacy->items()->count());

        $this->assertTrue(FeatureGate::allows($apartmentA, 'auto_dues', $user));
        $this->assertTrue(FeatureGate::allows($apartmentB, 'auto_dues', $user));
        $this->assertFalse(FeatureGate::allows($apartmentC, 'auto_dues', $user));

        $payment = SubscriptionPayment::query()->where('subscription_id', $order->id)->firstOrFail();
        $this->assertFalse(Schema::hasColumn('subscription_payments', 'apartment_id'));
        $this->assertEquals((float) $order->fresh()->price, (float) $payment->amount);
        $this->assertSame(1, SubscriptionPayment::query()->where('subscription_id', $order->id)->count());

        $this->assertNull($order->fresh()->subscription_id);
        $this->assertSame(3, Subscription::query()->count());

        $recordA = $freeA->apartmentSubscription->fresh();
        $recordB = $freeB->apartmentSubscription->fresh();
        $recordC = $freeC->apartmentSubscription->fresh();
        $this->assertSame($recordA->id, $paidA->apartment_subscription_id);
        $this->assertSame($recordB->id, $paidB->apartment_subscription_id);
        $this->assertSame($recordC->id, $outsidePending->apartment_subscription_id);
        $this->assertNotSame($recordA->id, $recordB->id);
        foreach ([$recordA, $recordB, $recordC] as $record) {
            $this->assertSame(Subscription::STATUS_ACTIVE, $record->status);
            $this->assertNull($record->ended_at);
        }
        $this->assertTrue($recordA->started_at->equalTo($startedA));
        $this->assertTrue($recordB->started_at->equalTo($startedB));
        $this->assertTrue($recordC->started_at->equalTo($startedC));
        $this->assertTrue($paidA->expires_at->equalTo($paidA->started_at->copy()->addYear()));
        $this->assertTrue($paidB->expires_at->equalTo($paidB->started_at->copy()->addYear()));
    }

    public function test_approval_keeps_the_original_subscription_start_and_sets_the_paid_term(): void
    {
        $this->travelTo('2026-11-01 10:00:00');

        [$user, $apartment, $free] = $this->freeApartment('A Blok');
        $admin = User::factory()->admin()->create();
        $record = $free->apartmentSubscription;
        $originalStart = '2026-10-04 09:15:00';
        $record->update(['started_at' => $originalStart]);

        $order = $this->pendingOrder($user, [$apartment], 'monthly');

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-AY',
            ])
            ->assertRedirect();

        $paid = $order->items()->firstOrFail()->fresh();
        $free->refresh();
        $record->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $record->status);
        $this->assertNull($record->ended_at);
        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame($record->id, $order->fresh()->subscription_id);
        $this->assertSame($record->id, $paid->apartment_subscription_id);
        $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $paid->status);
        $this->assertSame('2026-11-01 10:00:00', $paid->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 10:00:00', $paid->expires_at->format('Y-m-d H:i:s'));
        $this->assertNull($paid->ended_at);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->ended_at);
        $this->assertNull($free->subscription_id);

        $payment = SubscriptionPayment::query()->where('subscription_id', $order->id)->firstOrFail();
        $this->assertSame($order->id, $payment->subscription_id);
        $this->assertFalse(Schema::hasColumn('subscription_payments', 'apartment_id'));

        $this->travelTo('2026-12-15 11:30:00');
        $renewal = $this->pendingOrder($user, [$apartment], 'monthly');

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $renewal]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-YENILE',
            ])
            ->assertRedirect();

        $renewed = $renewal->items()->firstOrFail()->fresh();
        $paid->refresh();
        $record->refresh();

        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(Subscription::STATUS_ACTIVE, $record->status);
        $this->assertNull($record->ended_at);
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame($record->id, $renewed->apartment_subscription_id);
        $this->assertSame('2026-12-15 11:30:00', $renewed->started_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-15 11:30:00', $renewed->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(SubscriptionItem::STATUS_CANCELLED, $paid->status);
        $this->assertSame($renewed->started_at->format('Y-m-d H:i:s'), $paid->ended_at->format('Y-m-d H:i:s'));
    }

    public function test_a_failed_approval_rolls_back_the_subscription_and_the_items(): void
    {
        [$user, $apartment, $free] = $this->freeApartment('A Blok');
        $admin = User::factory()->admin()->create();
        $record = $free->apartmentSubscription;
        $originalStart = $record->started_at->format('Y-m-d H:i:s');
        $order = $this->pendingOrder($user, [$apartment], 'monthly');
        $record->update([
            'status' => Subscription::STATUS_ENDED,
            'ended_at' => '2026-10-03 08:00:00',
        ]);

        SubscriptionPayment::creating(function () {
            throw new RuntimeException('ödeme yazılamadı');
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($admin)
                ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                    'payment_method' => 'havale',
                    'reference_code' => 'HVL-FAIL',
                ]);
            $this->fail('Onay işlemi hata verince tamamlanmamalı.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ödeme yazılamadı', $exception->getMessage());
        } finally {
            SubscriptionPayment::flushEventListeners();
        }

        $record->refresh();
        $this->assertSame(Subscription::STATUS_ENDED, $record->status);
        $this->assertSame('2026-10-03 08:00:00', $record->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, Subscription::query()->count());

        $free->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->ended_at);

        $paid = $order->items()->firstOrFail()->fresh();
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $paid->status);
        $this->assertNull($paid->started_at);
        $this->assertNull($paid->ended_at);

        $order->refresh();
        $this->assertSame(UserSubscription::STATUS_PENDING, $order->status);
        $this->assertFalse($order->is_active);
        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_approval_does_not_create_a_subscription_for_a_legacy_apartment(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Eski Apartman',
            'unit_count' => 12,
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => Package::factory()->create()->id,
            'period' => 'monthly',
            'price' => 150,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'expires_at' => null,
        ]);
        SubscriptionItem::create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 12,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$manager, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-ESKI',
            ])
            ->assertRedirect();

        $this->assertSame(0, Subscription::query()->count());
        $this->assertNull($order->fresh()->subscription_id);
        $this->assertNull($order->items()->firstOrFail()->apartment_subscription_id);
        $this->assertSame(UserSubscription::STATUS_ACTIVE, $order->fresh()->status);
        $this->assertSame(1, SubscriptionPayment::query()->count());
    }

    public function test_a_second_pending_order_for_the_same_apartment_is_rejected(): void
    {
        [$user, $apartment] = $this->freeApartment('A Blok');
        $this->pendingOrder($user, [$apartment]);

        $this->expectException(ValidationException::class);

        $this->pendingOrder($user, [$apartment]);
    }

    public function test_rejecting_a_pending_order_keeps_the_free_period(): void
    {
        [$user, $apartment, $free] = $this->freeApartment('A Blok');
        $admin = User::factory()->admin()->create();
        $order = $this->pendingOrder($user, [$apartment]);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.reject', [$user, $order]), [
                'rejection_notes' => 'Dekont okunmuyor',
            ])
            ->assertRedirect();

        $free->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->ended_at);
        $this->assertNull($free->subscription_id);
        $this->assertFalse(FeatureGate::allows($apartment, 'auto_dues', $user));

        $paid = $order->items()->firstOrFail()->fresh();
        $this->assertSame(SubscriptionItem::STATUS_CANCELLED, $paid->status);
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $order->fresh()->status);

        $replacement = $this->pendingOrder($user, [$apartment]);
        $this->assertSame(SubscriptionItem::STATUS_PENDING, $replacement->items()->firstOrFail()->status);
        $free->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
    }

    public function test_approved_zero_amount_paid_item_opens_features_without_an_apartment_payment(): void
    {
        [$user, $apartment, $free] = $this->freeApartment('A Blok');
        $admin = User::factory()->admin()->create();
        $package = Package::factory()->create();

        $order = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'period' => 'monthly',
            'price' => 0,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'started_at' => now(),
            'expires_at' => null,
            'payment_method' => 'havale',
        ]);

        SubscriptionItem::create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 0,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-0',
            ])
            ->assertRedirect();

        $paid = $order->items()->firstOrFail()->fresh();
        $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
        $this->assertEquals(0, (float) $paid->amount);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $paid->status);
        $this->assertTrue(FeatureGate::allows($apartment, 'auto_dues', $user));

        $free->refresh();
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->subscription_id);
        $this->assertNull($free->ended_at);

        $payment = SubscriptionPayment::query()->where('subscription_id', $order->id)->firstOrFail();
        $this->assertEquals(0, (float) $payment->amount);
        $this->assertFalse(Schema::hasColumn('subscription_payments', 'apartment_id'));
        $this->assertSame($order->id, $payment->subscription_id);
    }

    public function test_an_expired_paid_period_returns_to_the_existing_free_item_without_a_new_order(): void
    {
        $this->travelTo('2026-10-05 10:00:00');

        [$user, $apartment, $free] = $this->freeApartment('A Blok');
        $admin = User::factory()->admin()->create();
        $record = $free->apartmentSubscription;
        $originalStart = $record->started_at->format('Y-m-d H:i:s');
        $order = $this->pendingOrder($user, [$apartment], 'monthly');

        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-BITIS',
            ])
            ->assertRedirect();

        $this->assertSame(1, UserSubscription::query()->count());
        $this->assertTrue(FeatureGate::allows($apartment->fresh(), 'auto_dues', $user));

        $this->travelTo('2026-12-01 10:00:00');

        $free->refresh();
        $record->refresh();
        $paid = $order->items()->firstOrFail()->fresh();

        $this->assertSame(1, UserSubscription::query()->count());
        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(2, SubscriptionItem::query()->count());
        $this->assertSame($originalStart, $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertNull($free->subscription_id);
        $this->assertNull($free->ended_at);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $paid->status);
        $this->assertFalse($paid->isCovering());
        $this->assertFalse(FeatureGate::allows($apartment->fresh(), 'auto_dues', $user));
        $this->assertTrue($record->currentItem()->is($free));
    }

    /**
     * @return array{0: User, 1: Apartment, 2: SubscriptionItem}
     */
    private function freeApartment(string $name, ?User $user = null): array
    {
        $user ??= User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => $name,
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

        app(SubscriptionCheckout::class)->openFree($user, $apartment);

        $free = SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan', SubscriptionItem::PLAN_FREE)
            ->firstOrFail();

        return [$user, $apartment, $free];
    }

    private function pendingOrder(User $user, array $apartments, string $period = 'yearly'): UserSubscription
    {
        return app(SubscriptionCheckout::class)->openPending(
            $user,
            collect($apartments),
            $period,
            'havale'
        );
    }
}
