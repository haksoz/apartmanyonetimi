<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionItemListTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_view_lists_each_subscription_item_from_its_own_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->create(['name' => 'Kurucu Kisi']);
        $owner = User::factory()->create(['name' => 'Guncel Yonetici', 'email' => 'guncel@example.com']);
        $former = User::factory()->create(['name' => 'Eski Yonetici']);
        $resident = User::factory()->create(['name' => 'Sakin Kisi']);
        $payer = User::factory()->create(['name' => 'Odeyen Kisi', 'email' => 'odeyen@example.com']);

        $apartment = Apartment::factory()->forUser($creator)->create([
            'name' => 'Canli Apartman',
            'unit_count' => 12,
            'billing_plan' => 'paid',
        ]);
        $apartment->members()->attach($former->id, ['role' => 'owner', 'is_active' => false]);
        $apartment->members()->attach($resident->id, ['role' => 'member', 'is_active' => true]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ODEYEN',
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'started_at' => '2020-01-01',
            'expires_at' => '2030-01-01',
        ]);

        SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => 'Eski Anlik Ad',
            'unit_count' => 12,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-03-01 10:00:00',
            'expires_at' => '2026-04-15 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertSee('Sipariş no')
            ->assertDontSee('Müşteri yönetimi')
            ->assertDontSee('Apartmanlar')
            ->assertSee('Abonelik No')
            ->assertSee('Canli Apartman')
            ->assertSeeInOrder(['Canli Apartman', '—', 'Guncel Yonetici'])
            ->assertDontSee('Eski Anlik Ad')
            ->assertSee('Guncel Yonetici')
            ->assertSee('Odeyen Kisi')
            ->assertSee('odeyen@example.com')
            ->assertSee('Ücretsiz')
            ->assertDontSee('Ücretli')
            ->assertSee('01.03.2026')
            ->assertSee('15.04.2026')
            ->assertDontSee('01.01.2030')
            ->assertDontSee('150')
            ->assertSee('Aktif')
            ->assertSee('SIP-26-ODEYEN')
            ->assertDontSee('Kurucu Kisi')
            ->assertDontSee('Eski Yonetici')
            ->assertDontSee('Sakin Kisi');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Abonelikler')
            ->assertDontSee('Paketler');
    }

    public function test_items_view_shows_subscription_number_separately_from_the_order_number(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Bagli Yonetici']);
        $payer = User::factory()->create(['name' => 'Bagli Odeyen']);
        $apartment = Apartment::factory()->forUser($owner)->create(['name' => 'Bagli Apartman']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $record = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-000001',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:00:00',
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-309232',
        ]);
        $this->makeItem($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 900,
            'started_at' => '2026-11-01',
            'expires_at' => '2026-12-01',
        ]);

        $legacyOwner = User::factory()->create(['name' => 'Eski Yonetici']);
        $legacyApartment = Apartment::factory()->forUser($legacyOwner)->create(['name' => 'Eski Apartman']);
        $legacyApartment->members()->attach($legacyOwner->id, ['role' => 'owner', 'is_active' => true]);
        $legacyOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ESKI',
        ]);
        $this->makeItem($legacyOrder, $legacyApartment, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 0,
        ]);

        $this->assertNotSame('ABN-26-000001', 'SIP-26-309232');
        $this->assertNull($order->fresh()->subscription_id);

        $html = $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Abonelik No')
            ->assertSee('Sipariş no')
            ->assertSee('Apartman')
            ->assertSee('Güncel yönetici')
            ->assertSee('Ödeyen')
            ->assertSee('Plan')
            ->assertSee('Başlangıç')
            ->assertSee('Bitiş')
            ->assertDontSee('Tutar')
            ->assertSee('Durum')
            ->assertSee('Detay')
            ->assertSee('ABN-26-000001')
            ->assertSee('Ücretli')
            ->assertSee('01.11.2026')
            ->assertSee('01.12.2026')
            ->assertDontSee('SIP-26-309232')
            ->assertSee('SIP-26-ESKI')
            ->assertSeeInOrder(['Bagli Apartman', 'ABN-26-000001', 'Bagli Yonetici', 'Bagli Odeyen'])
            ->assertSeeInOrder(['Eski Apartman', '—', 'Eski Yonetici', 'SIP-26-ESKI'])
            ->getContent();

        $this->assertSame(2, substr_count($html, 'ABN-26-000001'));
    }

    public function test_one_order_with_two_apartments_and_past_items_are_separate_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Ortak Odeyen']);
        $apartmentA = Apartment::factory()->forUser($payer)->create(['name' => 'A Blok']);
        $apartmentB = Apartment::factory()->forUser($payer)->create(['name' => 'B Blok']);
        $apartmentA->members()->attach($payer->id, ['role' => 'owner', 'is_active' => true]);
        $apartmentB->members()->attach($payer->id, ['role' => 'owner', 'is_active' => true]);

        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-AB',
        ]);

        $this->makeItem($order, $apartmentA, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 900,
            'started_at' => '2026-06-01',
        ]);
        $this->makeItem($order, $apartmentB, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'amount' => 300,
            'started_at' => null,
            'expires_at' => null,
        ]);

        $old = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ESKI',
        ]);
        $this->makeItem($old, $apartmentA, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'amount' => 0,
            'started_at' => '2025-01-01',
            'expires_at' => null,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('A Blok')
            ->assertSee('B Blok')
            ->assertSee('Ödeme bekliyor')
            ->assertSee('İptal')
            ->assertSee('SIP-26-ESKI')
            ->getContent();

        $this->assertSame(4, substr_count($html, 'SIP-26-AB'));
    }

    public function test_items_view_omits_apartments_and_orders_without_items(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        Apartment::factory()->forUser($manager)->create([
            'name' => 'Kalemsiz Apartman',
            'is_active' => true,
        ]);
        UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'order_number' => 'SIP-LEGACY-ONLY',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Abonelik yok.')
            ->assertDontSee('Kalemsiz Apartman')
            ->assertDontSee('SIP-LEGACY-ONLY');
    }

    public function test_deleted_apartment_uses_the_item_snapshot_name(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Snapshot Odeyen']);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-SILINDI',
        ]);

        SubscriptionItem::create([
            'subscription_id' => $order->id,
            'apartment_id' => null,
            'apartment_name' => 'Silinmis Apartman',
            'unit_count' => 8,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'started_at' => '2024-05-01',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Silinmis Apartman')
            ->assertSee('—')
            ->assertSee('Snapshot Odeyen')
            ->assertSee('Ücretli')
            ->assertSee('İptal');
    }

    public function test_items_are_ordered_by_started_at_then_id(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $order = UserSubscription::factory()->create(['user_id' => $payer->id, 'order_number' => 'SIP-26-SIRA']);

        $this->makeItem($order, Apartment::factory()->forUser($payer)->create(['name' => 'Mart Kalemi']), [
            'started_at' => '2026-03-01',
        ]);
        $this->makeItem($order, Apartment::factory()->forUser($payer)->create(['name' => 'Haziran Kalemi']), [
            'started_at' => '2026-06-01',
        ]);
        $this->makeItem($order, Apartment::factory()->forUser($payer)->create(['name' => 'Bos Eski']), [
            'started_at' => null,
        ]);
        $this->makeItem($order, Apartment::factory()->forUser($payer)->create(['name' => 'Bos Yeni']), [
            'started_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSeeInOrder(['Haziran Kalemi', 'Mart Kalemi', 'Bos Yeni', 'Bos Eski']);
    }

    public function test_items_view_searches_apartment_manager_payer_and_order_number(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Aranan Yonetici', 'email' => 'yonetici@example.com']);
        $payer = User::factory()->create(['name' => 'Aranan Odeyen', 'email' => 'odeyen-ara@example.com']);
        $other = User::factory()->create(['name' => 'Baska Kisi']);
        $apartment = Apartment::factory()->forUser($other)->create(['name' => 'Aranan Apartman']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        $hidden = Apartment::factory()->forUser($other)->create(['name' => 'Gizli Apartman']);
        $hidden->members()->attach($other->id, ['role' => 'owner', 'is_active' => true]);

        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ARAMA',
        ]);
        $this->makeItem($order, $apartment, ['apartment_name' => 'Eski Snapshot']);
        $otherOrder = UserSubscription::factory()->create([
            'user_id' => $other->id,
            'order_number' => 'SIP-26-GIZLI',
        ]);
        $this->makeItem($otherOrder, $hidden);

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Aranan Apartman']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('Gizli Apartman');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Eski Snapshot']))
            ->assertOk()->assertSee('Aranan Apartman')->assertDontSee('Gizli Apartman');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Aranan Yonetici']))
            ->assertOk()->assertSee('Aranan Apartman')->assertDontSee('Gizli Apartman');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'yonetici@example.com']))
            ->assertOk()->assertSee('Aranan Apartman');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Aranan Odeyen']))
            ->assertOk()->assertSee('SIP-26-ARAMA')->assertDontSee('Gizli Apartman');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'SIP-26-ARAMA']))
            ->assertOk()->assertSee('Aranan Apartman')->assertDontSee('SIP-26-GIZLI');

        $this->actingAs($admin)->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Baska Kisi']))
            ->assertOk()->assertSee('Gizli Apartman')->assertDontSee('Aranan Apartman');
    }

    public function test_items_view_paginates_subscription_items(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $order = UserSubscription::factory()->create(['user_id' => $payer->id, 'order_number' => 'SIP-26-SAYFA']);

        foreach (range(1, 21) as $number) {
            $name = sprintf('Kalem %02d', $number);
            $apartment = Apartment::factory()->forUser($payer)->create(['name' => $name]);
            $this->makeItem($order, $apartment, [
                'started_at' => sprintf('2026-01-%02d', $number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Kalem 21')
            ->assertDontSee('Kalem 01');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'page' => 2]))
            ->assertOk()
            ->assertSee('Kalem 01')
            ->assertDontSee('Kalem 21');
    }

    public function test_multiple_periods_of_one_subscription_are_a_single_list_row(): void
    {
        $this->travelTo('2026-10-05 12:00:00');

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Dertli Yonetici']);
        $formerPayer = User::factory()->create(['name' => 'Eski Odeyen']);
        $payer = User::factory()->create(['name' => 'Guncel Odeyen', 'email' => 'guncel-odeyen@example.com']);
        $apartment = Apartment::factory()->forUser($owner)->create(['name' => 'Dertli Deliler']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        $record = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-000001',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:00:00',
        ]);
        $freeOrder = UserSubscription::factory()->create([
            'user_id' => $formerPayer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-103224',
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'price' => 0,
        ]);
        $this->makeItem($freeOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'amount' => 0,
            'started_at' => '2026-10-04',
            'expires_at' => '2026-10-05',
            'ended_at' => '2026-10-05 10:00:00',
        ]);
        $paidOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 150,
        ]);
        $paid = $this->makeItem($paidOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 150,
            'started_at' => '2026-10-05',
            'expires_at' => '2026-11-05',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Dertli Deliler')
            ->assertSee('ABN-26-000001')
            ->assertSee('Dertli Yonetici')
            ->assertSee('Guncel Odeyen')
            ->assertSee('Ücretli')
            ->assertSee('05.10.2026')
            ->assertSee('05.11.2026')
            ->assertDontSee('150')
            ->assertSee('Aktif')
            ->assertDontSee('Ücretsiz')
            ->assertDontSee('İptal')
            ->assertDontSee('SIP-26-103224')
            ->assertDontSee('SIP-26-300918')
            ->assertDontSee('Bekleyen sipariş')
            ->assertDontSee('Eski Odeyen')
            ->assertDontSee('04.10.2026')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'ABN-26-000001'));
        $this->assertSame(2, substr_count($html, 'Dertli Deliler'));
        $this->assertSame($paid->id, $record->currentItem()->id);

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'ABN-26-000001']))
            ->assertOk()
            ->assertSee('Dertli Deliler');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Dertli Deliler']))
            ->assertOk()
            ->assertSee('ABN-26-000001');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Dertli Yonetici']))
            ->assertOk()
            ->assertSee('ABN-26-000001');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Guncel Odeyen']))
            ->assertOk()
            ->assertSee('ABN-26-000001');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'SIP-26-103224']))
            ->assertOk()
            ->assertDontSee('ABN-26-000001');

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items', 'search' => 'Eski Odeyen']))
            ->assertOk()
            ->assertDontSee('ABN-26-000001');

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.show', $record))
            ->assertOk()
            ->assertSee('Abonelik Detayı')
            ->assertSee('Dönem geçmişi')
            ->assertSee('ABN-26-000001')
            ->assertSee('04.10.2026')
            ->assertSee('Ücretli')
            ->assertSee('Ücretsiz')
            ->assertSee('150')
            ->assertSee('Ücretsiz · 0 ₺')
            ->assertSee('Aktif')
            ->assertSee('İptal')
            ->assertSee('SIP-26-300918')
            ->assertSee('SIP-26-103224')
            ->assertSee('Guncel Odeyen')
            ->assertSee('Eski Odeyen')
            ->assertSeeInOrder(['Ücretli · 150 ₺', 'Ücretsiz · 0 ₺'])
            ->assertSee('05.10.2026 - 05.11.2026')
            ->assertSee('04.10.2026 - 05.10.2026')
            ->assertSee(route('admin.orders.show', $paidOrder), false);

        $legacy = $this->makeItem(
            UserSubscription::factory()->create([
                'user_id' => $payer->id,
                'order_number' => 'SIP-26-LEGACY',
            ]),
            Apartment::factory()->forUser($payer)->create(['name' => 'Legacy Apartman']),
            ['plan' => SubscriptionItem::PLAN_FREE, 'status' => SubscriptionItem::STATUS_ACTIVE]
        );

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $legacy))
            ->assertOk()
            ->assertSee('Legacy Apartman')
            ->assertSee('SIP-26-LEGACY')
            ->assertDontSee('ABN-26-000001');
    }

    public function test_status_warns_when_an_active_subscription_has_a_pending_order(): void
    {
        $this->travelTo('2026-10-05 12:00:00');

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Dertli Yonetici']);
        $payer = User::factory()->create(['name' => 'Guncel Odeyen']);
        $apartment = Apartment::factory()->forUser($owner)->create(['name' => 'Dertli Deliler']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        $record = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-000001',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:00:00',
        ]);
        $paidOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 150,
        ]);
        $this->makeItem($paidOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 150,
            'started_at' => '2026-10-05',
            'expires_at' => '2026-11-05',
        ]);
        $pendingOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-BEKLEYEN',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 150,
        ]);
        $this->makeItem($pendingOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'amount' => 150,
            'started_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index', ['view' => 'items']))
            ->assertOk()
            ->assertSee('Aktif')
            ->assertSee('rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Bekleyen sipariş', false)
            ->assertDontSee('SIP-26-BEKLEYEN')
            ->assertDontSee('SIP-26-300918');
    }

    private function makeItem(UserSubscription $subscription, Apartment $apartment, array $overrides = []): SubscriptionItem
    {
        return SubscriptionItem::create(array_merge([
            'subscription_id' => $subscription->id,
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
