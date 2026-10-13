<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderBillingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_detail_shows_the_snapshot_and_only_acceptances_for_that_order(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profile = $this->profile($ahmet, 'Profil A', 'A Apartmanı Yönetimi');
        $profile->update([
            'email' => 'fatura@ornek.test',
            'phone' => '02160000000',
            'country' => 'Türkiye',
            'province' => 'İstanbul',
            'district' => 'Kadıköy',
            'address' => 'Moda Cad. 5',
            'postal_code' => '34710',
        ]);
        $order = $this->place($ahmet, $apartment, $profile);

        $other = UserSubscription::factory()->create([
            'user_id' => $ahmet->id,
            'order_number' => 'SIP-BASKA',
        ]);
        LegalAcceptance::query()->create([
            'user_id' => $ahmet->id,
            'user_subscription_id' => $other->id,
            'document_key' => LegalConsent::DISTANCE_SALES,
            'document_version' => '99-baska-siparis',
            'accepted_at' => now(),
        ]);
        LegalAcceptance::query()->create([
            'user_id' => $ahmet->id,
            'user_subscription_id' => null,
            'document_key' => LegalConsent::MEMBERSHIP,
            'document_version' => '88-uye-kabul',
            'accepted_at' => now(),
        ]);

        $receipt = $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Sipariş Anındaki Fatura Bilgileri')
            ->assertSee('A Apartmanı Yönetimi')
            ->assertSee('Kurum')
            ->assertSee('1234567890')
            ->assertSee('Kadıköy')
            ->assertSee('fatura@ornek.test')
            ->assertSee('Moda Cad. 5')
            ->assertSee('34710')
            ->assertSee('Mesafeli satış sözleşmesi')
            ->assertSee('Ön bilgilendirme formu')
            ->assertDontSee('99-baska-siparis')
            ->assertDontSee('88-uye-kabul')
            ->assertDontSee('Bu sipariş için fatura bilgisi kayıtlı değil.')
            ->assertDontSee('Bu siparişe bağlı sözleşme kabul kaydı yok.');

        $this->assertStringNotContainsString('name="billing_label"', $receipt->getContent());

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Sipariş Anındaki Fatura Bilgileri')
            ->assertSee('A Apartmanı Yönetimi')
            ->assertSee('Moda Cad. 5')
            ->assertSee('Mesafeli satış sözleşmesi')
            ->assertDontSee('99-baska-siparis')
            ->assertDontSee('88-uye-kabul');

        $profile->update([
            'legal_name' => 'YENI-UNVAN-ZX',
            'address' => 'Yeni Adres Sokak',
            'is_active' => false,
        ]);

        $order->refresh();
        $this->assertSame('A Apartmanı Yönetimi', $order->billing_legal_name);
        $this->assertSame('Moda Cad. 5', $order->billing_address);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('A Apartmanı Yönetimi')
            ->assertSee('Moda Cad. 5')
            ->assertDontSee('YENI-UNVAN-ZX')
            ->assertDontSee('Yeni Adres Sokak');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('A Apartmanı Yönetimi')
            ->assertDontSee('YENI-UNVAN-ZX');
    }

    public function test_an_order_without_a_snapshot_does_not_borrow_the_current_profile(): void
    {
        $ahmet = User::factory()->create(['name' => 'Ahmet']);
        BillingProfile::query()->create([
            'user_id' => $ahmet->id,
            'label' => 'Canli Profil',
            'party_type' => BillingProfile::TYPE_CORPORATE,
            'legal_name' => 'CANLI-PROFIL-ZX',
            'is_active' => true,
        ]);
        $order = UserSubscription::factory()->create([
            'user_id' => $ahmet->id,
            'order_number' => 'SIP-ESKI-BOS',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'billing_profile_id' => null,
            'billing_label' => null,
            'billing_legal_name' => null,
            'billing_party_type' => null,
        ]);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Bu sipariş için fatura bilgisi kayıtlı değil.')
            ->assertSee('Bu siparişe bağlı sözleşme kabul kaydı yok.')
            ->assertDontSee('CANLI-PROFIL-ZX')
            ->assertDontSee('Canli Profil');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Bu sipariş için fatura bilgisi kayıtlı değil.')
            ->assertDontSee('CANLI-PROFIL-ZX');
    }

    public function test_another_customer_cannot_open_the_order_and_a_manager_change_keeps_the_old_order(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profile = $this->profile($ahmet, 'Ahmet Profil A', 'A Apartmanı Yönetimi');
        $order = $this->place($ahmet, $apartment, $profile);
        $frozen = $order->only(['user_id', 'billing_legal_name', 'billing_label', 'billing_address']);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertForbidden();

        $mehmet = User::factory()->create(['name' => 'Mehmet']);
        $apartment->members()->updateExistingPivot($ahmet->id, ['role' => 'member', 'is_active' => false]);
        $apartment->members()->attach($mehmet->id, ['role' => 'owner', 'is_active' => true]);

        $order->refresh();
        $this->assertSame($frozen['user_id'], $order->user_id);
        $this->assertSame('A Apartmanı Yönetimi', $order->billing_legal_name);
        $this->assertSame($apartment->id, $order->items()->first()->apartment_id);

        $this->actingAs($mehmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Ahmet')
            ->assertSee('A Apartmanı Yönetimi')
            ->assertSee($apartment->name);
    }

    public function test_admin_order_list_shows_and_searches_the_billing_recipient_without_marking_an_invoice(): void
    {
        $admin = User::factory()->admin()->create();
        $payer = User::factory()->create(['name' => 'Liste Odeyen']);
        $matched = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-LISTE-VAR',
            'status' => UserSubscription::STATUS_PENDING,
            'is_active' => false,
            'billing_label' => 'Liste Profili',
            'billing_legal_name' => 'Liste Unvani Ltd',
        ]);
        $hidden = UserSubscription::factory()->create([
            'user_id' => $payer->id,
            'order_number' => 'SIP-LISTE-YOK',
            'status' => UserSubscription::STATUS_ACTIVE,
            'billing_label' => null,
            'billing_legal_name' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Fatura alıcısı')
            ->assertSee('Liste Unvani Ltd')
            ->assertSee('Liste Profili')
            ->assertSee('SIP-LISTE-YOK')
            ->assertDontSee('Fatura kesildi');

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => 'Liste Unvani']))
            ->assertOk()
            ->assertSee('SIP-LISTE-VAR')
            ->assertDontSee('SIP-LISTE-YOK');

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => 'Liste Profili']))
            ->assertOk()
            ->assertSee($matched->order_number)
            ->assertDontSee($hidden->order_number);

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => 'SIP-LISTE-YOK', 'status' => 'active']))
            ->assertOk()
            ->assertSee('SIP-LISTE-YOK')
            ->assertDontSee('SIP-LISTE-VAR');
    }

    public function test_cancel_leaves_the_snapshot_in_place(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profile = $this->profile($ahmet, 'Profil A', 'A Apartmanı Yönetimi');
        $order = $this->place($ahmet, $apartment, $profile);

        $this->actingAs($ahmet)
            ->post(route('subscriber.subscriptions.cancel', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $order->status);
        $this->assertSame('A Apartmanı Yönetimi', $order->billing_legal_name);
        $this->assertSame('Profil A', $order->billing_label);
    }

    /**
     * @return array{0: User, 1: Apartment, 2: Subscription}
     */
    private function apartmentFor(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'A Apartmanı',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        $subscription = app(SubscriptionCheckout::class)->openFree($user, $apartment);

        return [$user, $apartment, $subscription];
    }

    private function profile(User $user, string $label, string $legalName): BillingProfile
    {
        return BillingProfile::query()->create([
            'user_id' => $user->id,
            'label' => $label,
            'party_type' => BillingProfile::TYPE_CORPORATE,
            'legal_name' => $legalName,
            'identity_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'is_active' => true,
        ]);
    }

    private function place(User $user, Apartment $apartment, BillingProfile $profile): UserSubscription
    {
        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => 'havale',
                'accept_sales' => '1',
                'billing_mode' => 'existing',
                'billing_profile_id' => $profile->id,
            ])
            ->assertRedirect();

        return UserSubscription::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }
}
