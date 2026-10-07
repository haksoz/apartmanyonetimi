<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_customer_is_visible_in_the_admin_list(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Panel Yoneticisi']);
        $customer = User::factory()->create([
            'name' => 'Yeni Musteri',
            'email' => 'yeni@example.com',
            'phone' => '5550001122',
            'created_at' => '2026-10-01 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Müşteriler')
            ->assertSee(route('admin.customers.index'), false)
            ->assertSeeInOrder([
                'Yeni Musteri',
                'yeni@example.com',
                '5550001122',
                '01.10.2026',
                'Apartman',
                '0',
                'Aktif abonelik',
                '0',
                'Bekleyen sipariş',
                '0',
                'Detay',
            ])
            ->assertSee(route('admin.customers.show', $customer), false);
    }

    public function test_customer_detail_opens(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Detay Musteri',
            'email' => 'detay@example.com',
            'created_at' => '2026-10-02 10:00:00',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Müşteri bilgileri')
            ->assertSee('Detay Musteri')
            ->assertSee('detay@example.com')
            ->assertSee('02.10.2026')
            ->assertSee('Yönetici');
    }

    public function test_customer_without_an_apartment_does_not_error(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Bos Musteri',
            'email' => 'bos@example.com',
            'phone' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSeeInOrder(['Apartmanlar', '0', 'Aktif abonelikler', '0', 'Bekleyen siparişler', '0', 'Toplam siparişler', '0'])
            ->assertSee('Apartman yok.')
            ->assertSee('Abonelik yok.')
            ->assertSee('Sipariş yok.')
            ->assertSee('Bu müşteriye bağlı tahsilat yok.')
            ->assertSee('Bilgileri kaydet')
            ->assertSee('Parolayı güncelle');
    }

    public function test_customer_with_one_apartment_is_listed(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Tek Apartman Musteri']);
        $apartment = Apartment::factory()->forUser($customer)->create(['name' => 'Dertli Deliler Apartmanı']);
        $this->member($customer, $apartment);
        $record = $this->subscription($apartment, 'ABN-26-000001');
        $order = $this->order($customer, [
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 150,
        ]);
        $this->item($order, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'amount' => 150,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSeeInOrder(['Tek Apartman Musteri', 'Apartman', '1', 'Aktif abonelik', '1']);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Dertli Deliler Apartmanı')
            ->assertSee('ABN-26-000001')
            ->assertSee('Aktif')
            ->assertSee('Ücretli')
            ->assertSee('Yönetici')
            ->assertSee('01.01.2020 - 01.01.2030')
            ->assertSee(route('apartments.show', $apartment), false)
            ->assertSee(route('admin.subscriptions.show', $record), false);
    }

    public function test_customer_with_multiple_apartments_lists_each_one(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Cok Apartman Musteri']);
        $first = Apartment::factory()->forUser($customer)->create(['name' => 'Dertli Deliler Apartmanı']);
        $second = Apartment::factory()->forUser($customer)->create(['name' => 'Sarıkayalar Apartmanı']);
        $this->member($customer, $first);
        $this->member($customer, $second);
        $paid = $this->subscription($first, 'ABN-26-000001');
        $free = $this->subscription($second, 'ABN-26-000002');
        $paidOrder = $this->order($customer, [
            'subscription_id' => $paid->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        $freeOrder = $this->order($customer, [
            'subscription_id' => $free->id,
            'order_number' => 'SIP-26-UCRETSIZ',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 0,
        ]);
        $this->item($paidOrder, $first, ['apartment_subscription_id' => $paid->id, 'plan' => SubscriptionItem::PLAN_PAID]);
        $this->item($freeOrder, $second, [
            'apartment_subscription_id' => $free->id,
            'plan' => SubscriptionItem::PLAN_FREE,
            'amount' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $response->assertOk()->assertSeeInOrder(['Apartmanlar', '2', 'Aktif abonelikler', '2']);

        $apartments = $this->section($response->getContent(), 'customer-apartments', 'customer-subscriptions');
        $this->assertStringContainsString('Dertli Deliler Apartmanı', $apartments);
        $this->assertStringContainsString('ABN-26-000001', $apartments);
        $this->assertStringContainsString('Ücretli', $apartments);
        $this->assertStringContainsString('Sarıkayalar Apartmanı', $apartments);
        $this->assertStringContainsString('ABN-26-000002', $apartments);
        $this->assertStringContainsString('Ücretsiz', $apartments);
    }

    public function test_customer_subscriptions_are_shown_once_per_subscription(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Abonelik Musteri']);
        $apartment = Apartment::factory()->forUser($customer)->create(['name' => 'Dertli Deliler Apartmanı']);
        $this->member($customer, $apartment);
        $record = $this->subscription($apartment, 'ABN-26-000007');

        $oldOrder = $this->order($customer, [
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-103224',
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'price' => 0,
        ]);
        $currentOrder = $this->order($customer, [
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 150,
        ]);
        $this->item($oldOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'amount' => 0,
            'started_at' => '2026-09-01',
            'expires_at' => null,
            'ended_at' => '2026-10-05 07:37:00',
        ]);
        $this->item($currentOrder, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_PAID,
            'amount' => 150,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));
        $response->assertOk();

        $subscriptions = $this->section($response->getContent(), 'customer-subscriptions', 'customer-orders');
        $this->assertSame(2, substr_count($subscriptions, 'ABN-26-000007'));
        $this->assertStringContainsString('Dertli Deliler Apartmanı', $subscriptions);
        $this->assertStringContainsString('Aktif', $subscriptions);
        $this->assertStringContainsString('Ücretli', $subscriptions);
        $this->assertStringNotContainsString('Ücretsiz', $subscriptions);
        $this->assertStringContainsString(route('admin.subscriptions.show', $record), $subscriptions);
    }

    public function test_customer_orders_and_existing_payments_are_shown(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Siparis Musteri']);
        $apartment = Apartment::factory()->forUser($customer)->create(['name' => 'Dertli Deliler Apartmanı']);
        $this->member($customer, $apartment);
        $record = $this->subscription($apartment, 'ABN-26-000001');
        $order = $this->order($customer, [
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-300918',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 150,
            'created_at' => '2026-10-05 09:00:00',
        ]);
        $this->item($order, $apartment, ['apartment_subscription_id' => $record->id, 'amount' => 150]);
        SubscriptionPayment::create([
            'subscription_id' => $order->id,
            'amount' => 150,
            'payment_date' => '2026-10-05 10:00:00',
            'payment_method' => 'nakit',
            'reference_code' => 'NKT-20261005-073743-INZJ',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSeeInOrder(['Siparis Musteri', 'Bekleyen sipariş', '1']);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));
        $response->assertOk()->assertSeeInOrder(['Bekleyen siparişler', '1', 'Toplam siparişler', '1']);

        $orders = $this->section($response->getContent(), 'customer-orders', 'customer-payments');
        $this->assertStringContainsString('SIP-26-300918', $orders);
        $this->assertStringContainsString('05.10.2026', $orders);
        $this->assertStringContainsString('150,00', $orders);
        $this->assertStringContainsString('Bekliyor', $orders);
        $this->assertStringContainsString('Tahsil edildi', $orders);
        $this->assertStringContainsString('Dertli Deliler Apartmanı', $orders);
        $this->assertStringContainsString('ABN-26-000001', $orders);
        $this->assertStringContainsString(route('admin.orders.show', $order), $orders);

        $payments = $this->section($response->getContent(), 'customer-payments', null);
        $this->assertStringContainsString('NKT-20261005-073743-INZJ', $payments);
        $this->assertStringContainsString('Nakit', $payments);
        $this->assertStringContainsString('150,00', $payments);
    }

    public function test_multi_apartment_order_keeps_every_apartment(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Coklu Siparis Musteri']);
        $first = Apartment::factory()->forUser($customer)->create(['name' => 'Dertli Deliler Apartmanı']);
        $second = Apartment::factory()->forUser($customer)->create(['name' => 'Sarıkayalar Apartmanı']);
        $this->member($customer, $first);
        $this->member($customer, $second);
        $recordA = $this->subscription($first, 'ABN-26-000001');
        $recordB = $this->subscription($second, 'ABN-26-000002');
        $order = $this->order($customer, [
            'subscription_id' => null,
            'order_number' => 'SIP-26-COKLU',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'price' => 450,
        ]);
        $this->item($order, $first, ['apartment_subscription_id' => $recordA->id, 'amount' => 200]);
        $this->item($order, $second, ['apartment_subscription_id' => $recordB->id, 'amount' => 250]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));
        $response->assertOk();

        $orders = $this->section($response->getContent(), 'customer-orders', 'customer-payments');
        $this->assertStringContainsString('SIP-26-COKLU', $orders);
        $this->assertStringContainsString('Dertli Deliler Apartmanı', $orders);
        $this->assertStringContainsString('Sarıkayalar Apartmanı', $orders);
        $this->assertStringContainsString('ABN-26-000001', $orders);
        $this->assertStringContainsString('ABN-26-000002', $orders);
        $this->assertStringContainsString(route('admin.orders.show', $order), $orders);
        $this->assertNull($order->fresh()->subscription_id);
    }

    public function test_legacy_relations_do_not_break_the_customer_page(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Eski Musteri']);
        Apartment::factory()->forUser($customer)->create(['name' => 'Legacy UserId Apartmani']);
        $order = $this->order($customer, [
            'subscription_id' => null,
            'order_number' => 'SIP-26-ESKI',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 80,
        ]);
        SubscriptionItem::create([
            'subscription_id' => $order->id,
            'apartment_id' => null,
            'apartment_subscription_id' => null,
            'apartment_name' => 'Eski Kalem Apartmani',
            'unit_count' => 4,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 80,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => '2020-01-01',
            'expires_at' => '2030-01-01',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

        $response->assertOk()
            ->assertSee('Eski Musteri')
            ->assertSee('Apartman yok.')
            ->assertSee('Abonelik yok.')
            ->assertSee('SIP-26-ESKI')
            ->assertSee('Eski Kalem Apartmani')
            ->assertSee('—')
            ->assertDontSee('Legacy UserId Apartmani')
            ->assertDontSee('ABN-');
    }

    public function test_customer_search_matches_name_email_and_phone(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create([
            'name' => 'Mahir Sarıkaya',
            'email' => 'mahir@ko.com.tr',
            'phone' => '5551112233',
        ]);
        User::factory()->create([
            'name' => 'Baska Musteri',
            'email' => 'baska@example.com',
            'phone' => '5559998877',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => 'Sarıkaya']))
            ->assertOk()
            ->assertSee('Mahir Sarıkaya')
            ->assertDontSee('Baska Musteri');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => 'mahir@ko.com.tr']))
            ->assertOk()
            ->assertSee('Mahir Sarıkaya')
            ->assertDontSee('Baska Musteri');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['search' => '5551112233']))
            ->assertOk()
            ->assertSee('Mahir Sarıkaya')
            ->assertDontSee('Baska Musteri');
    }

    public function test_customer_list_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 21) as $number) {
            User::factory()->create([
                'name' => sprintf('Sayfa Musteri %02d', $number),
                'created_at' => now()->subMinutes(21 - $number),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Sayfa Musteri 21')
            ->assertDontSee('Sayfa Musteri 01');

        $this->actingAs($admin)
            ->get(route('admin.customers.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Sayfa Musteri 01');
    }

    public function test_free_basic_use_is_not_counted_as_a_customer_order(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['name' => 'Temel Musteri']);
        $apartment = Apartment::factory()->forUser($customer)->create(['name' => 'Temel Apartman']);
        $this->member($customer, $apartment);
        $record = app(\App\Support\SubscriptionCheckout::class)->openFree($customer, $apartment);

        $legacyFree = $this->order($customer, [
            'subscription_id' => $record->id,
            'order_number' => 'SIP-26-UCRETSIZ',
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'price' => 0,
        ]);
        $this->item($legacyFree, $apartment, [
            'apartment_subscription_id' => $record->id,
            'plan' => SubscriptionItem::PLAN_FREE,
            'amount' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSeeInOrder(['Temel Musteri', 'Apartman', '1', 'Aktif abonelik', '1', 'Bekleyen sipariş', '0']);

        $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));
        $response->assertOk()
            ->assertSee('Temel Apartman')
            ->assertSee($record->subscription_no)
            ->assertSeeInOrder(['Bekleyen siparişler', '0', 'Toplam siparişler', '0'])
            ->assertSee('Sipariş yok.')
            ->assertDontSee('SIP-26-UCRETSIZ');
    }

    public function test_admin_can_update_customer_profile_without_changing_the_password(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Eski Ad',
            'email' => 'eski@example.com',
            'phone' => '5550000000',
            'password' => 'eski-parola',
        ]);
        $password = $customer->password;

        $this->actingAs($admin)
            ->patch(route('admin.customers.update', $customer), [
                'name' => 'Yeni Ad',
                'email' => 'yeni-ad@example.com',
                'phone' => '5551234567',
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHas('status', 'Müşteri bilgileri güncellendi.');

        $customer->refresh();
        $this->assertSame('Yeni Ad', $customer->name);
        $this->assertSame('yeni-ad@example.com', $customer->email);
        $this->assertSame('5551234567', $customer->phone);
        $this->assertSame($password, $customer->password);
        $this->assertTrue(Hash::check('eski-parola', $customer->password));

        $this->actingAs($admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Yeni Ad')
            ->assertSee('yeni-ad@example.com')
            ->assertSee('5551234567');
    }

    public function test_customer_profile_rejects_an_email_that_belongs_to_someone_else(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'musteri@example.com']);
        User::factory()->create(['email' => 'dolu@example.com']);

        $this->actingAs($admin)
            ->from(route('admin.customers.show', $customer))
            ->patch(route('admin.customers.update', $customer), [
                'name' => $customer->name,
                'email' => 'dolu@example.com',
                'phone' => '',
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHasErrors('email');

        $this->assertSame('musteri@example.com', $customer->fresh()->email);
    }

    public function test_admin_can_update_customer_password(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Parola Musteri',
            'password' => 'eski-parola',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.customers.password.update', $customer), [
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHas('status', 'Parola güncellendi.');

        $customer->refresh();
        $this->assertSame('Parola Musteri', $customer->name);
        $this->assertTrue(Hash::check('yeni-parola', $customer->password));
        $this->assertFalse(Hash::check('eski-parola', $customer->password));
    }

    public function test_customer_password_must_be_confirmed(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['password' => 'eski-parola']);

        $this->actingAs($admin)
            ->from(route('admin.customers.show', $customer))
            ->followingRedirects()
            ->patch(route('admin.customers.password.update', $customer), [
                'password' => 'yeni-parola',
                'password_confirmation' => 'baska-parola',
            ])
            ->assertOk()
            ->assertSee('The password field confirmation does not match.')
            ->assertSee('id="customer-password-modal" data-open="1"', false);

        $this->assertTrue(Hash::check('eski-parola', $customer->fresh()->password));
    }

    public function test_unauthorized_users_cannot_open_customers(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.customers.index'))
            ->assertRedirect(route('login'));

        $this->get(route('admin.customers.show', $customer))
            ->assertRedirect(route('login'));

        $this->actingAs($customer)
            ->get(route('admin.customers.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.customers.show', $customer))
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin.customers.update', $customer), [
                'name' => 'Yetkisiz',
                'email' => 'yetkisiz@example.com',
            ])
            ->assertForbidden();

        $this->actingAs($customer)
            ->patch(route('admin.customers.password.update', $customer), [
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertForbidden();

        $this->assertNotSame('Yetkisiz', $customer->fresh()->name);
    }

    private function member(User $user, Apartment $apartment): void
    {
        $apartment->members()->attach($user->id, [
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    private function subscription(Apartment $apartment, string $number): Subscription
    {
        return Subscription::create([
            'apartment_id' => $apartment->id,
            'subscription_no' => $number,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => '2026-10-04 09:00:00',
        ]);
    }

    private function order(User $customer, array $overrides = []): UserSubscription
    {
        return UserSubscription::factory()->create(array_merge([
            'user_id' => $customer->id,
            'price' => 150,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ], $overrides));
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
            'started_at' => '2020-01-01',
            'expires_at' => '2030-01-01',
        ], $overrides));
    }

    private function section(string $html, string $startId, ?string $endId): string
    {
        $start = strpos($html, 'id="'.$startId.'"');
        $this->assertNotFalse($start);

        if ($endId === null) {
            return substr($html, $start);
        }

        $end = strpos($html, 'id="'.$endId.'"');
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
