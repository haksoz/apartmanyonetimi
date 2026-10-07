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
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Nasıl ödemek istersiniz?')
            ->assertSee('Havale / EFT')
            ->assertSee('Kredi kartı')
            ->assertSee('Henüz aktif değil')
            ->assertSee('value="kredi_kartı" class="mt-1" disabled', false)
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

        $html = $this->actingAs($manager)
            ->followingRedirects()
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
            ])
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Siparişiniz alındı. Havale/EFT ödemesi için banka bilgilerini görüntüleyebilirsiniz.'));

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
            ->assertSeeText('Havale/EFT açıklama kısmına')
            ->assertSee('Ödeme bilgisi gir')
            ->assertSee('Sipariş no');
    }

    public function test_receipt_page_highlights_the_apartment(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Belirgin Apartman',
            'address' => 'Ordu Sokak',
            'district' => 'Kartal',
            'province' => 'İstanbul',
            'unit_count' => 8,
        ]);
        $record = \App\Models\Subscription::create([
            'apartment_id' => $apartment->id,
            'status' => \App\Models\Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
        $subscription = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
        ]);
        SubscriptionItem::create([
            'subscription_id' => $subscription->id,
            'apartment_subscription_id' => $record->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 8,
            'band_label' => '1–14 daire',
            'band_min_units' => 1,
            'amount' => 150,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_PENDING,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee('Belirgin Apartman')
            ->assertSee('8 daire')
            ->assertSee('Ordu Sokak')
            ->assertSee('Kartal / İstanbul')
            ->assertSee($record->subscription_no);
    }

    public function test_receipt_page_shows_only_the_opened_order(): void
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $first = UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'order_number' => 'SIP-26-BIRINCI',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
        ]);
        UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'order_number' => 'SIP-26-IKINCI',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $first))
            ->assertOk()
            ->assertSee('SIP-26-BIRINCI')
            ->assertDontSee('SIP-26-IKINCI');
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
        $this->assertSame(UserSubscription::STATUS_PENDING, $subscription->status);
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

    public function test_pending_order_without_a_receipt_can_upload_one(): void
    {
        Storage::fake('public');
        [$manager, $subscription] = $this->pendingOrder();

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'receipt' => UploadedFile::fake()->image('dekont.png'),
            ])
            ->assertRedirect();

        $subscription->refresh();
        $this->assertNotNull($subscription->receipt_path);
        $this->assertTrue(Storage::disk('public')->exists($subscription->receipt_path));
        $this->assertSame(UserSubscription::STATUS_PENDING, $subscription->status);
    }

    public function test_a_second_receipt_is_rejected_after_one_was_saved(): void
    {
        Storage::fake('public');
        [$manager, $subscription] = $this->pendingOrder([
            'receipt_path' => 'receipts/1/ilk.png',
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'receipt' => UploadedFile::fake()->image('ikinci.png'),
            ])
            ->assertSessionHasErrors('payment_info');

        $this->assertSame('receipts/1/ilk.png', $subscription->fresh()->receipt_path);
    }

    public function test_a_reference_cannot_be_changed_after_it_was_saved(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'receipt_reference' => 'REF-ILK',
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'reference_code' => 'REF-YENI',
            ])
            ->assertSessionHasErrors('payment_info');

        $this->assertSame('REF-ILK', $subscription->fresh()->receipt_reference);
    }

    public function test_cancel_is_rejected_when_a_receipt_was_submitted(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'receipt_path' => 'receipts/1/dekont.png',
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.cancel', $subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(UserSubscription::STATUS_PENDING, $subscription->fresh()->status);
    }

    public function test_cancel_is_rejected_when_a_reference_was_submitted(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'receipt_reference' => 'REF-ILK',
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.cancel', $subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(UserSubscription::STATUS_PENDING, $subscription->fresh()->status);
    }

    public function test_payment_info_is_rejected_for_an_active_order(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'receipt_reference' => 'REF-ONAY',
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'reference_code' => 'REF-YENI',
            ])
            ->assertSessionHasErrors('payment_info');

        $this->assertSame('REF-ONAY', $subscription->fresh()->receipt_reference);
        $this->assertSame(UserSubscription::STATUS_ACTIVE, $subscription->fresh()->status);
    }

    public function test_payment_info_is_rejected_for_a_cancelled_order(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'ended_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.payment-info', $subscription), [
                'reference_code' => 'REF-YENI',
            ])
            ->assertSessionHasErrors('payment_info');

        $this->assertNull($subscription->fresh()->receipt_reference);
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $subscription->fresh()->status);
    }

    public function test_cancel_is_rejected_for_an_active_order(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.cancel', $subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(UserSubscription::STATUS_ACTIVE, $subscription->fresh()->status);
    }

    public function test_cancel_is_rejected_for_a_cancelled_order(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'status' => UserSubscription::STATUS_CANCELLED,
            'is_active' => false,
            'ended_at' => now(),
        ]);

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.cancel', $subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(UserSubscription::STATUS_CANCELLED, $subscription->fresh()->status);
    }

    public function test_a_pending_order_without_payment_proof_can_be_cancelled(): void
    {
        [$manager, $subscription] = $this->pendingOrder();

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.cancel', $subscription))
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertSame(UserSubscription::CANCELLED_BY_CUSTOMER, $subscription->cancelled_by);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $subscription))
            ->assertOk()
            ->assertSee('Müşteri iptal etti')
            ->assertDontSee('Admin iptal etti');
    }

    public function test_order_list_shows_review_waiting_after_payment_proof(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'receipt_reference' => 'REF-INCELEME',
            'order_number' => 'SIP-26-INCELEME',
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk()
            ->assertSee('Onay bekliyor')
            ->assertSee('SIP-26-INCELEME')
            ->assertDontSee('Ödeme Gir')
            ->assertDontSee('Bekliyor');
    }

    public function test_havale_receipt_page_offers_cancel_until_payment_proof_is_sent(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'payment_method' => 'havale',
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee('Ödeme bekliyor')
            ->assertSee('Siparişi iptal et')
            ->assertDontSee('Onay bekliyor');

        $subscription->update(['receipt_reference' => 'REF-GONDERILDI']);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee('Onay bekliyor')
            ->assertSee('Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.')
            ->assertDontSee('Siparişi iptal et')
            ->assertDontSee('Ödeme bilgisi gir');
    }

    public function test_credit_card_order_does_not_show_a_receipt_form(): void
    {
        [$manager, $subscription] = $this->pendingOrder([
            'payment_method' => 'kredi_kartı',
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee('Kredi kartı ödemesi henüz aktif değil.')
            ->assertDontSee('Ödeme bilgisi gir')
            ->assertDontSee('Siparişi iptal et')
            ->assertDontSee('name="receipt"', false)
            ->assertDontSee('name="reference_code"', false);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{0: User, 1: UserSubscription}
     */
    private function pendingOrder(array $extra = []): array
    {
        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();
        $subscription = UserSubscription::factory()->create(array_merge([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'payment_method' => 'havale',
            'receipt_path' => null,
            'receipt_reference' => null,
        ], $extra));

        return [$manager, $subscription];
    }
}
