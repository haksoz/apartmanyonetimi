<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\LegalAcceptance;
use App\Models\LegalDocumentVersion;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\LegalPlaceholders;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class LegalPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_documents_are_filled_from_the_order_snapshot_and_then_frozen(): void
    {
        $this->publishCurrent();
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        $profile->update([
            'legal_name' => 'PROFIL-UNVAN',
            'identity_number' => '11111111111',
            'tax_office' => 'Yakacık',
            'email' => 'fatura@ornek.test',
            'phone' => '02160000000',
            'address' => 'Ordu Sokak 21',
            'district' => 'Kartal',
            'province' => 'İstanbul',
        ]);

        $order = $this->open($ahmet, $apartment, $profile, 'yearly', 'havale');
        $order->update(['billing_legal_name' => 'SNAPSHOT-UNVAN']);
        $profile->update(['legal_name' => 'PROFIL-SONRASI']);
        app(LegalConsent::class)->recordSale($ahmet, $order->fresh(), Request::create('/siparis', 'POST'));

        $distance = $this->acceptance($order, LegalConsent::DISTANCE_SALES);
        $pre = $this->acceptance($order, LegalConsent::PRE_INFORMATION);
        $price = number_format((float) $order->price, 2, ',', '.').' ₺';

        $this->assertStringContainsString('Alıcı / Tüketici: SNAPSHOT-UNVAN', $distance->accepted_content);
        $this->assertStringContainsString('Kimlik / Vergi No: 11111111111', $distance->accepted_content);
        $this->assertStringContainsString('Vergi Dairesi: Yakacık', $distance->accepted_content);
        $this->assertStringContainsString('Adres: Ordu Sokak 21, Kartal, İstanbul', $distance->accepted_content);
        $this->assertStringContainsString('E-posta: fatura@ornek.test', $distance->accepted_content);
        $this->assertStringContainsString('Telefon: 02160000000', $distance->accepted_content);
        $this->assertStringNotContainsString('PROFIL-UNVAN', $distance->accepted_content);
        $this->assertStringNotContainsString('PROFIL-SONRASI', $distance->accepted_content);

        $this->assertStringContainsString('Apartman: A Apartmanı', $pre->accepted_content);
        $this->assertStringContainsString('Daire sayısı: 10', $pre->accepted_content);
        $this->assertStringContainsString('Hizmet dönemi: 12 aylık', $pre->accepted_content);
        $this->assertStringContainsString('Toplam bedel (vergiler dahil): '.$price, $pre->accepted_content);
        $this->assertStringContainsString('Ödeme yöntemi: Havale / EFT', $pre->accepted_content);
        $this->assertStringContainsString('Sipariş no: '.$order->order_number, $pre->accepted_content);
        $this->assertStringContainsString('Hizmet başlangıcı: '.LegalPlaceholders::PENDING_START, $pre->accepted_content);
        $this->assertStringContainsString('Hizmet bitişi: '.LegalPlaceholders::PENDING_END, $pre->accepted_content);
        $this->assertStringContainsString('2 (iki) gün', $distance->accepted_content);
        $this->assertStringContainsString('Online ödemelerde ödeme başarılı olduğunda', $pre->accepted_content);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Kabul edilen metin')
            ->assertSee('SNAPSHOT-UNVAN')
            ->assertSee('Apartman: A Apartmanı')
            ->assertDontSee('Bu kabul sırasında belge metni saklanmamış.');

        $profile->update(['legal_name' => 'YENI-PROFIL-UNVAN', 'is_active' => false]);
        $apartment->update(['name' => 'B Apartmanı', 'unit_count' => 40]);
        $frozenDistance = $distance->accepted_content;
        $frozenPre = $pre->accepted_content;
        $order->update([
            'price' => 1,
            'period' => 'monthly',
            'payment_method' => 'kredi_kartı',
            'billing_legal_name' => 'SIPARIS-SONRASI-UNVAN',
            'order_number' => 'SIP-DEGISTI',
        ]);
        $order->items()->update(['apartment_name' => 'C Apartmanı', 'unit_count' => 3]);

        $this->assertSame($frozenDistance, $distance->fresh()->accepted_content);
        $this->assertSame($frozenPre, $pre->fresh()->accepted_content);
        $this->assertStringNotContainsString('SIPARIS-SONRASI-UNVAN', $distance->fresh()->accepted_content);
        $this->assertStringNotContainsString('C Apartmanı', $pre->fresh()->accepted_content);

        try {
            $distance->fresh()->update(['accepted_content' => 'degisti']);
            $this->fail('Kabul metni güncellenebildi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Kabul edilen sözleşme metni değiştirilemez.', $exception->getMessage());
        }
        $this->assertSame($frozenDistance, $distance->fresh()->accepted_content);
    }

    public function test_a_havale_acceptance_does_not_keep_concrete_service_dates(): void
    {
        $this->publishCurrent();
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        $order = $this->place($ahmet, $apartment, $profile, 'havale');
        $content = $this->acceptance($order, LegalConsent::PRE_INFORMATION)->accepted_content;

        $this->assertStringContainsString('Hizmet başlangıcı: '.LegalPlaceholders::PENDING_START, $content);
        $this->assertStringContainsString('Hizmet bitişi: '.LegalPlaceholders::PENDING_END, $content);

        $this->approve($ahmet, $order);
        $order->refresh();
        $this->assertNotNull($order->expires_at);
        $this->assertStringNotContainsString(
            $order->expires_at->timezone(config('app.timezone'))->format('d.m.Y'),
            $this->acceptance($order, LegalConsent::PRE_INFORMATION)->accepted_content,
        );
        $this->assertSame($content, $this->acceptance($order, LegalConsent::PRE_INFORMATION)->accepted_content);
    }

    public function test_an_activated_online_order_freezes_the_approval_date(): void
    {
        $this->publishCurrent();
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        $checkout = app(SubscriptionCheckout::class);
        $order = $checkout->openPending($ahmet, collect([$apartment]), 'monthly', 'kredi_kartı', $profile->fresh());
        $checkout->activate($order);
        $order = $order->fresh();
        app(LegalConsent::class)->recordSale($ahmet, $order, Request::create('/siparis', 'POST'));

        $start = $order->started_at->timezone(config('app.timezone'))->format('d.m.Y');
        $end = $order->expires_at->timezone(config('app.timezone'))->format('d.m.Y');
        $content = $this->acceptance($order, LegalConsent::PRE_INFORMATION)->accepted_content;

        $this->assertStringContainsString('Hizmet başlangıcı: '.$start, $content);
        $this->assertStringContainsString('Hizmet bitişi: '.$end, $content);
        $this->assertStringContainsString('Ödeme yöntemi: Kredi kartı', $content);
        $this->assertStringNotContainsString('Hizmet başlangıcı: '.LegalPlaceholders::PENDING_START, $content);

        $order->update([
            'started_at' => now()->addMonth(),
            'expires_at' => now()->addYear(),
        ]);
        $this->assertSame($content, $this->acceptance($order, LegalConsent::PRE_INFORMATION)->fresh()->accepted_content);
    }

    public function test_an_old_version_one_acceptance_is_not_backfilled(): void
    {
        $distanceV1 = $this->publish(LegalConsent::DISTANCE_SALES, '1', 'distance-sales-v1.txt');
        $preV1 = $this->publish(LegalConsent::PRE_INFORMATION, '1', 'pre-information-v1.txt');
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        $order = $this->open($ahmet, $apartment, $profile, 'monthly', 'havale');

        foreach ([[$distanceV1, LegalConsent::DISTANCE_SALES], [$preV1, LegalConsent::PRE_INFORMATION]] as [$version, $key]) {
            LegalAcceptance::query()->create([
                'user_id' => $ahmet->id,
                'user_subscription_id' => $order->id,
                'document_key' => $key,
                'document_version' => '1',
                'legal_document_version_id' => $version->id,
                'accepted_content' => null,
                'accepted_at' => now(),
            ]);
        }

        $v1Body = $distanceV1->body;
        $v1Hash = $distanceV1->content_sha256;
        $this->publishCurrent();

        $this->assertSame($v1Body, $distanceV1->fresh()->body);
        $this->assertSame($v1Hash, $distanceV1->fresh()->content_sha256);
        $this->assertNull($this->acceptance($order, LegalConsent::DISTANCE_SALES)->accepted_content);
        $this->assertSame('1', $this->acceptance($order, LegalConsent::DISTANCE_SALES)->document_version);

        $order->items()->update(['status' => SubscriptionItem::STATUS_CANCELLED]);
        $profile->update(['identity_number' => '99887766551', 'legal_name' => 'YENI-SIPARIS-UNVAN']);
        $second = $this->place($ahmet, $apartment, $profile->fresh(), 'havale');

        $this->assertSame($distanceV1->id, $this->acceptance($order, LegalConsent::DISTANCE_SALES)->legal_document_version_id);
        $this->assertNull($this->acceptance($order, LegalConsent::PRE_INFORMATION)->accepted_content);
        $this->assertNotNull($this->acceptance($second, LegalConsent::DISTANCE_SALES)->accepted_content);
        $this->assertStringContainsString('99887766551', $this->acceptance($second, LegalConsent::DISTANCE_SALES)->accepted_content);
        $this->assertSame('2', $this->acceptance($second, LegalConsent::DISTANCE_SALES)->document_version);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Bu kabul sırasında belge metni saklanmamış.')
            ->assertDontSee('99887766551')
            ->assertDontSee('Kabul edilen metin');
    }

    public function test_an_unknown_placeholder_is_rejected(): void
    {
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        $order = $this->open($ahmet, $apartment, $profile, 'monthly', 'havale');

        try {
            LegalPlaceholders::render('Gizli {{secret_field}}', LegalPlaceholders::fromOrder($order));
            $this->fail('Bilinmeyen alan dolduruldu.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Bilinmeyen sözleşme alanı: secret_field', $exception->getMessage());
        }

        $this->assertSame(0, LegalAcceptance::query()->count());
    }

    public function test_public_pages_explain_placeholders_and_the_checkout_previews_the_selection(): void
    {
        $this->publishCurrent();
        [$ahmet, $apartment, $profile] = $this->apartmentFor();
        Subscription::query()->where('apartment_id', $apartment->id)->update([
            'billing_profile_id' => $profile->id,
        ]);

        $this->get(route('legal.distance-sales'))
            ->assertOk()
            ->assertSee('Bu metin genel şablondur')
            ->assertSee('Fatura alıcısı / unvan')
            ->assertDontSee('{{billing_legal_name}}')
            ->assertDontSee('A Apartmanı Yönetimi');

        $this->get(route('legal.pre-information'))
            ->assertOk()
            ->assertSee('Siparişte seçilen apartman')
            ->assertSee('Aylık veya 12 aylık')
            ->assertDontSee('{{apartment_name}}')
            ->assertDontSee('[Siparişte seçilen apartman]');

        $this->actingAs($ahmet)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Alıcı / Tüketici:', 'A Apartmanı Yönetimi'])
            ->assertSeeInOrder(['Apartman:', 'A Apartmanı'])
            ->assertSeeInOrder(['Daire sayısı:', '10'])
            ->assertSee('Hizmet başlangıcı: ')
            ->assertSee(LegalPlaceholders::PENDING_START)
            ->assertSee(LegalPlaceholders::PREVIEW_ORDER_NUMBER)
            ->assertDontSee('{{apartment_name}}')
            ->assertDontSee('{{billing_legal_name}}');
    }

    private function publishCurrent(): void
    {
        $this->publish(LegalConsent::DISTANCE_SALES, '2', 'distance-sales-v2.txt');
        $this->publish(LegalConsent::PRE_INFORMATION, '2', 'pre-information-v2.txt');
    }

    private function publish(string $key, string $version, string $file): LegalDocumentVersion
    {
        $body = file_get_contents(base_path('tests/Fixtures/legal/'.$file));
        $this->assertNotFalse($body);
        $title = $key === LegalConsent::DISTANCE_SALES ? 'Mesafeli Satış Sözleşmesi' : 'Ön Bilgilendirme Formu';

        return LegalDocumentVersion::publish($key, $version, $title, $body);
    }

    private function acceptance(UserSubscription $order, string $key): LegalAcceptance
    {
        return LegalAcceptance::query()
            ->where('user_subscription_id', $order->id)
            ->where('document_key', $key)
            ->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Apartment, 2: BillingProfile}
     */
    private function apartmentFor(): array
    {
        $user = User::factory()->create(['name' => 'Ahmet']);
        $apartment = Apartment::factory()->forUser($user)->create([
            'name' => 'A Apartmanı',
            'unit_count' => 10,
            'billing_plan' => 'free',
            'is_active' => true,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        $profile = BillingProfile::query()->create([
            'user_id' => $user->id,
            'label' => 'Profil A',
            'party_type' => BillingProfile::TYPE_CORPORATE,
            'legal_name' => 'A Apartmanı Yönetimi',
            'is_active' => true,
        ]);

        return [$user, $apartment, $profile];
    }

    private function open(User $user, Apartment $apartment, BillingProfile $profile, string $period, string $method): UserSubscription
    {
        return app(SubscriptionCheckout::class)->openPending(
            $user,
            collect([$apartment]),
            $period,
            $method,
            $profile->fresh(),
        );
    }

    private function place(User $user, Apartment $apartment, BillingProfile $profile, string $method): UserSubscription
    {
        $this->actingAs($user)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [$apartment->id],
                'period' => 'yearly',
                'payment_method' => $method,
                'accept_sales' => '1',
                'billing_mode' => 'existing',
                'billing_profile_id' => $profile->id,
            ])
            ->assertRedirect();

        return UserSubscription::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function approve(User $user, UserSubscription $order): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.approve', [$user, $order]), [
                'payment_method' => 'havale',
                'reference_code' => 'HVL-SOZLESME',
            ])
            ->assertRedirect();
    }
}
