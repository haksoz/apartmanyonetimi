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

class SubscriptionItemDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_item_detail_shows_the_order_payment_and_a_list_link(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Guncel Yonetici']);
        $payer = User::factory()->create(['name' => 'Odeyen Kisi', 'email' => 'odeyen@example.com']);
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Detay Apartmani']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-DETAY',
            'price' => 900,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'payment_method' => 'havale',
            'receipt_reference' => 'HVL-DETAY',
            'receipt_path' => 'receipts/1/dekont.png',
        ]);
        $item = $this->item($subscription, $apartment, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'amount' => 900,
            'started_at' => '2026-03-01',
            'expires_at' => '2027-03-01',
        ]);
        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'amount' => 900,
            'payment_date' => '2026-03-02',
            'payment_method' => 'havale',
            'reference_code' => 'HVL-DETAY',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertOk()
            ->assertSee(route('admin.subscription-items.show', $item), false);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('Abonelik Detayı')
            ->assertSee('Abonelik No')
            ->assertSee('Detay Apartmani')
            ->assertSee('Ücretli')
            ->assertSee('Aktif')
            ->assertSee('01.03.2026')
            ->assertSee('01.03.2027')
            ->assertSee('Guncel Yonetici')
            ->assertSee('Odeyen Kisi')
            ->assertSee('odeyen@example.com')
            ->assertSee('SIP-26-DETAY')
            ->assertSee('900,00')
            ->assertSee('Havale / EFT')
            ->assertSee('HVL-DETAY')
            ->assertSee('Dekontu aç')
            ->assertSee('02.03.2026');
    }

    public function test_pending_item_detail_shows_waiting_status_without_a_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Bekleyen Odeyen']);
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Bekleyen Apartman']);
        $apartment->members()->attach($payer->id, ['role' => 'owner', 'is_active' => true]);
        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-BEKLE',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
        ]);
        $item = $this->item($subscription, $apartment, [
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
            'started_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('Bekliyor')
            ->assertSee('Bekleyen Apartman')
            ->assertSee('SIP-26-BEKLE')
            ->assertSee('Bu siparişe bağlı tahsilat yok.');
    }

    public function test_cancelled_item_detail_shows_the_close_time(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create();
        $apartment = Apartment::factory()->forUser($payer)->create(['name' => 'Kapanan Apartman']);
        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-IPTAL',
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
        ]);
        $item = $this->item($subscription, $apartment, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'ended_at' => '2026-04-15 14:30:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('İptal')
            ->assertSee('Ücretsiz')
            ->assertSee('Kapanış')
            ->assertSee('15.04.2026 14:30');
    }

    public function test_detail_keeps_the_payer_separate_from_the_current_manager(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->create(['name' => 'Kurucu Kisi']);
        $owner = User::factory()->create(['name' => 'Mehmet Yonetici']);
        $former = User::factory()->create(['name' => 'Eski Yonetici']);
        $payer = User::factory()->create(['name' => 'Hasan Odeyen', 'email' => 'hasan@example.com']);
        $apartment = Apartment::factory()->forUser($creator)->create(['name' => 'C Apartmani']);
        $apartment->members()->attach($former->id, ['role' => 'owner', 'is_active' => false]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-FARKLI',
        ]);
        $item = $this->item($subscription, $apartment);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('Mehmet Yonetici')
            ->assertSee('Hasan Odeyen')
            ->assertSee('hasan@example.com')
            ->assertDontSee('Kurucu Kisi')
            ->assertDontSee('Eski Yonetici');
    }

    public function test_detail_survives_a_deleted_apartment(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Snapshot Odeyen']);
        $subscription = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-SILIK',
        ]);

        $item = SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'apartment_id' => null,
            'apartment_name' => 'Silinmis Apartman',
            'unit_count' => 8,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_CANCELLED,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('Silinmis Apartman')
            ->assertSee('Snapshot Odeyen')
            ->assertSee('—');
    }

    public function test_missing_item_returns_not_found(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', 999999))
            ->assertNotFound();
    }

    public function test_detail_shows_the_permanent_subscription_number_and_keeps_legacy_items_readable(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Kalici Yonetici']);
        $payer = User::factory()->create(['name' => 'Kalici Odeyen']);
        $apartment = Apartment::factory()->forUser($owner)->create(['name' => 'Kontrol Apartmani']);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        $record = Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => 'ABN-26-000002',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-01 09:00:00',
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-KALICI',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $item = $this->item($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2026-10-01',
            'expires_at' => '2026-10-31',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $item))
            ->assertOk()
            ->assertSee('ABN-26-000002')
            ->assertSee('Kontrol Apartmani')
            ->assertSee('Kalici Yonetici')
            ->assertSee('Kalici Odeyen')
            ->assertSee('01.10.2026')
            ->assertSee('31.10.2026')
            ->assertSee('Ücretli')
            ->assertSee('Apartman detayına git')
            ->assertSee('Sipariş detayına git')
            ->assertDontSee('Yenile');

        $legacyOrder = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-26-ESKI',
        ]);
        $legacy = $this->item($legacyOrder, $apartment, [
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscription-items.show', $legacy))
            ->assertOk()
            ->assertSee('SIP-26-ESKI')
            ->assertSee('Ücretsiz')
            ->assertDontSee('ABN-26-000002');
    }

    private function item(UserSubscription $subscription, Apartment $apartment, array $overrides = []): SubscriptionItem
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
