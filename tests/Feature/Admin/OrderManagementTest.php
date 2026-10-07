<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_list_opens_and_shows_the_order_number_from_the_linked_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Bagli Odeyen', 'email' => 'odeyen@example.com', 'phone' => '5551112233']);
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Bagli Apartman']);
        $record = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-000111',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:00:00',
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-309232',
            'price' => 900,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'created_at' => '2026-03-12 10:00:00',
        ]);
        $this->item($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'amount' => 900,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Siparişler')
            ->assertSee('Bu ticari işlem ne durumda?')
            ->assertSee(route('admin.orders.index'), false)
            ->assertSee('Abonelikler')
            ->assertSeeInOrder(['SIP-26-309232', 'ABN-26-000111', 'Bagli Apartman', 'Bagli Odeyen'])
            ->assertSee('12.03.2026')
            ->assertSee('900,00')
            ->assertSee('Onaylandı')
            ->assertSee('Detay');
    }

    public function test_unlinked_multi_apartment_order_does_not_invent_a_subscription_number_on_the_list(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Coklu Odeyen']);
        $first = Apartment::factory()->forUser($payer)->create(['name' => 'A Apartman']);
        $second = Apartment::factory()->forUser($payer)->create(['name' => 'B Apartman']);
        $recordA = $this->subscription($first, 'ABN-26-100001');
        $recordB = $this->subscription($second, 'ABN-26-100002');
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => null,
            'order_number' => 'SIP-26-COKLU',
            'price' => 450,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);
        $this->item($order, $first, ['apartment_subscription_id' => $recordA->id, 'amount' => 200]);
        $this->item($order, $second, ['apartment_subscription_id' => $recordB->id, 'amount' => 250]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('SIP-26-COKLU')
            ->assertSee('A Apartman')
            ->assertSee('B Apartman')
            ->assertSee('Coklu Odeyen')
            ->assertDontSee('ABN-26-100001')
            ->assertDontSee('ABN-26-100002');
    }

    public function test_legacy_orders_without_items_or_a_subscription_still_render(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Eski Odeyen']);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => null,
            'order_number' => 'SIP-26-ESKI',
            'price' => 80,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('SIP-26-ESKI')
            ->assertSee('Eski Odeyen')
            ->assertDontSee('ABN-');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('SIP-26-ESKI')
            ->assertSee('Eski Odeyen')
            ->assertSee('Bu kayıtta sipariş kalemi yok.')
            ->assertSee('Bu siparişe bağlı tahsilat yok.')
            ->assertSee('—')
            ->assertDontSee('Abonelik Detayına Git')
            ->assertDontSee('ABN-');
    }

    public function test_free_basic_use_is_not_listed_as_an_order(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Temel Kullanan']);
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Temel Apartman']);
        $apartment->members()->attach($payer->id, ['role' => 'owner', 'is_active' => true]);
        app(\App\Support\SubscriptionCheckout::class)->openFree($payer, $apartment);

        $paid = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-GERCEK',
            'price' => 150,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $this->item($paid, $apartment, ['amount' => 150]);

        $legacyFree = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-UCRETSIZ',
            'price' => 0,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'notes' => 'Ücretsiz kullanım',
        ]);
        $this->item($legacyFree, $apartment, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'amount' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('SIP-26-GERCEK')
            ->assertDontSee('SIP-26-UCRETSIZ');

        $this->assertSame(1, SubscriptionItem::query()->whereNull('subscription_id')->where('plan', SubscriptionItem::PLAN_FREE)->count());
    }

    public function test_order_detail_shows_each_line_of_a_multi_apartment_order(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Kalem Odeyen', 'email' => 'kalem@example.com', 'phone' => '5550001122']);
        $first = Apartment::factory()->forUser($payer)->create(['name' => 'Birinci Apartman']);
        $second = Apartment::factory()->forUser($payer)->create(['name' => 'Ikinci Apartman']);
        $recordA = $this->subscription($first, 'ABN-26-200001');
        $recordB = $this->subscription($second, 'ABN-26-200002');
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => null,
            'order_number' => 'SIP-26-KALEM',
            'price' => 450,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'created_at' => '2026-04-02 09:00:00',
        ]);
        $firstItem = $this->item($order, $first, [
            'apartment_subscription_id' => $recordA->id,
            'amount' => 200,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-04-02',
            'expires_at' => '2026-05-02',
        ]);
        $secondItem = $this->item($order, $second, [
            'apartment_subscription_id' => $recordB->id,
            'amount' => 250,
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_PENDING,
            'started_at' => '2026-06-01',
            'expires_at' => '2026-07-01',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Sipariş Detayı')
            ->assertSee('SIP-26-KALEM')
            ->assertSee('02.04.2026')
            ->assertSee('450,00')
            ->assertSee('Kalem Odeyen')
            ->assertSee('kalem@example.com')
            ->assertSee('5550001122')
            ->assertSee('Birinci Apartman')
            ->assertSee('Ikinci Apartman')
            ->assertSee('ABN-26-200001')
            ->assertSee('ABN-26-200002')
            ->assertSee('200,00')
            ->assertSee('250,00')
            ->assertSee('Ücretli')
            ->assertSee('Ücretsiz')
            ->assertSee('02.04.2026 – 02.05.2026')
            ->assertSee('01.06.2026 – 01.07.2026')
            ->assertSee('Birden fazla dönem')
            ->assertSee('Birden fazla')
            ->assertSee(route('admin.subscription-items.show', $firstItem), false)
            ->assertSee(route('admin.subscription-items.show', $secondItem), false)
            ->assertSee(route('apartments.show', $first), false)
            ->assertSee(route('apartments.show', $second), false)
            ->assertDontSee('ABN-26-200001/');
    }

    public function test_order_detail_shows_payment_records_and_the_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Tahsilat Apartman']);
        $record = $this->subscription($apartment, 'ABN-26-300001');
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-ODEME',
            'price' => 900,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'payment_method' => 'havale',
            'receipt_reference' => 'HVL-DETAY',
            'receipt_path' => 'receipts/1/dekont.png',
        ]);
        $item = $this->item($order, $apartment, ['apartment_subscription_id' => $record->id, 'amount' => 900]);
        SubscriptionPayment::create([
            'subscription_id' => $order->id,
            'amount' => 900,
            'payment_date' => '2026-03-02',
            'payment_method' => 'havale',
            'reference_code' => 'HVL-DETAY',
            'notes' => 'Banka dekontu',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Tahsil edildi')
            ->assertSee('02.03.2026')
            ->assertSee('900,00')
            ->assertSee('Havale / EFT')
            ->assertSee('HVL-DETAY')
            ->assertSee('Dekontu aç')
            ->assertSee('Banka dekontu')
            ->assertSee(route('admin.subscription-items.show', $item), false)
            ->assertSee('Abonelik Detayına Git')
            ->assertSee('Apartman Detayına Git');

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee(route('admin.orders.show', $order), false);
    }

    public function test_pending_order_approval_keeps_the_existing_subscription(): void
    {
        $this->travelTo('2026-11-01 10:00:00');

        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Onay Apartman', 'unit_count' => 12]);
        $record = $this->subscription($apartment, 'ABN-26-000501', '2026-10-04 09:15:00');
        $freeOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-FREE',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 0,
        ]);
        $free = $this->item($freeOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'amount' => 0,
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:15:00',
            'expires_at' => null,
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-ONAY',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 150,
            'period' => 'monthly',
            'expires_at' => null,
            'payment_method' => 'havale',
        ]);
        $paid = $this->item($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'amount' => 150,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'started_at' => null,
            'expires_at' => null,
        ]);

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->fresh()->status);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Siparişi onayla')
            ->assertSee('Siparişi reddet')
            ->assertSee(route('admin.managers.subscription.approve', [$payer, $order]), false);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.managers.subscription.approve', [$payer, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-SIP',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status');

        $record->refresh();
        $free->refresh();
        $paid->refresh();
        $order->refresh();

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame($record->id, $order->subscription_id);
        $this->assertSame($record->id, $paid->apartment_subscription_id);
        $this->assertSame('2026-10-04 09:15:00', $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(Subscription::STATUS_ACTIVE, $record->status);
        $this->assertNull($record->ended_at);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $paid->status);
        $this->assertSame(SubscriptionItem::PLAN_PAID, $paid->plan);
        $this->assertSame('2026-11-01 10:00:00', $paid->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(SubscriptionItem::STATUS_CANCELLED, $free->status);
        $this->assertSame($paid->started_at->format('Y-m-d H:i:s'), $free->ended_at->format('Y-m-d H:i:s'));
        $this->assertSame(UserSubscription::STATUS_ACTIVE, $order->status);
        $this->assertTrue($order->is_active);
        $this->assertSame('HVL-SIP', $order->payments()->first()->reference_code);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_pending_order_can_be_rejected_from_the_order_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Red Apartman']);
        $record = $this->subscription($apartment, 'ABN-26-000601', '2026-10-04 09:15:00');
        $freeOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-FREE2',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 0,
        ]);
        $free = $this->item($freeOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:15:00',
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-RED',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 150,
        ]);
        $paid = $this->item($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'started_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.managers.subscription.reject', [$payer, $order]), [
                'rejection_notes' => 'Dekont yok',
            ])
            ->assertRedirect(route('admin.orders.show', $order));

        $record->refresh();
        $free->refresh();
        $paid->refresh();
        $order->refresh();

        $this->assertSame(1, Subscription::query()->count());
        $this->assertSame('2026-10-04 09:15:00', $record->started_at->format('Y-m-d H:i:s'));
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $order->status);
        $this->assertSame(SubscriptionItem::STATUS_CANCELLED, $paid->status);
        $this->assertSame(SubscriptionItem::STATUS_ACTIVE, $free->status);
        $this->assertSame(0, $order->payments()->count());
        $this->assertSame('Dekont yok', $order->notes);
        $this->assertSame(UserSubscription::CANCELLED_BY_ADMIN, $order->cancelled_by);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Admin iptal etti')
            ->assertDontSee('Müşteri iptal etti');
    }

    public function test_orders_can_be_filtered_and_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Aranan Odeyen', 'email' => 'aranan@example.com']);
        $other = User::factory()->create(['name' => 'Gizli Odeyen']);
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Aranan Apartman']);
        $hiddenApartment = Apartment::factory()->forUser($other)->create(['name' => 'Gizli Apartman']);

        $matched = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ARAMA',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 150,
        ]);
        $this->item($matched, $apartment, ['apartment_name' => 'Eski Snapshot']);
        $hidden = UserSubscription::factory()->create([
            'user_id' => $other->id,
            'order_number' => 'SIP-26-GIZLI',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 300,
        ]);
        $this->item($hidden, $hiddenApartment);
        SubscriptionPayment::create([
            'subscription_id' => $hidden->id,
            'amount' => 300,
            'payment_date' => now(),
            'payment_method' => 'havale',
            'reference_code' => 'HVL-GIZLI',
        ]);
        $cancelled = UserSubscription::factory()->create([
            'user_id' => $other->id,
            'order_number' => 'SIP-26-IPTAL',
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'SIP-26-ARAMA']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'Aranan Apartman']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'Eski Snapshot']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'Aranan Odeyen']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['search' => 'aranan@example.com']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['status' => 'pending']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI')->assertDontSee('SIP-26-IPTAL');

        $this->actingAs($admin)->get(route('admin.orders.index', ['payment' => 'collected']))
            ->assertOk()->assertSee('SIP-26-GIZLI')->assertDontSee('SIP-26-ARAMA');

        $this->actingAs($admin)->get(route('admin.orders.index', ['payment' => 'pending']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.orders.index', ['payment' => 'unpaid']))
            ->assertOk()->assertSee('SIP-26-IPTAL')->assertDontSee('SIP-26-ARAMA')->assertDontSee('SIP-26-GIZLI');

        foreach (range(1, 21) as $number) {
            UserSubscription::factory()->create([
                'user_id' => $payer->id,
                'order_number' => sprintf('SIP-26-SAYFA%02d', $number),
                'created_at' => now()->subMinutes(30 - $number),
                'updated_at' => now()->subMinutes(30 - $number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('SIP-26-SAYFA21')
            ->assertDontSee('SIP-26-SAYFA01');

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('SIP-26-SAYFA01');
    }

    public function test_unauthorized_users_cannot_open_orders(): void
    {
        $payer = User::factory()->create();
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-YETKI',
        ]);

        $this->get(route('admin.orders.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($payer)
            ->get(route('admin.orders.index'))
            ->assertForbidden();

        $this->actingAs($payer)
            ->get(route('admin.orders.show', $order))
            ->assertForbidden();
    }

    private function subscription(Apartment $apartment, string $number, string $startedAt = '2026-10-04 09:00:00'): Subscription
    {
        return Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => $number,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => $startedAt,
        ]);
    }

    private function item(UserSubscription $order, Apartment $apartment, array $overrides = []): SubscriptionItem
    {
        return SubscriptionItem::create(array_merge([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-02-01',
            'expires_at' => '2027-02-01',
        ], $overrides));
    }
}
