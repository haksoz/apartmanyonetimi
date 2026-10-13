<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\LegalAcceptance;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PublishedSaleDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_one_of_the_sale_documents_is_what_the_order_accepts(): void
    {
        $distance = $this->publish(LegalConsent::DISTANCE_SALES, 'Mesafeli Satış Sözleşmesi', 'distance-sales-v1.txt');
        $pre = $this->publish(LegalConsent::PRE_INFORMATION, 'Ön Bilgilendirme Formu', 'pre-information-v1.txt');

        $this->get(route('legal.distance-sales'))
            ->assertOk()
            ->assertSee('Mesafeli Satış Sözleşmesi')
            ->assertSee('AİDATCEP MESAFELİ SATIŞ SÖZLEŞMESİ')
            ->assertSee('MADDE 9 – CAYMA HAKKI')
            ->assertDontSee('Bu sayfa için içerik hazırlanmaktadır.');

        $this->get(route('legal.pre-information'))
            ->assertOk()
            ->assertSee('Ön Bilgilendirme Formu')
            ->assertSee('AİDATCEP ÖN BİLGİLENDİRME FORMU')
            ->assertSee('101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanabilir.')
            ->assertDontSee('Bu sayfa için içerik hazırlanmaktadır.');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');

        $this->actingAs($ahmet)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('AİDATCEP MESAFELİ SATIŞ SÖZLEŞMESİ')
            ->assertSee('AİDATCEP ÖN BİLGİLENDİRME FORMU')
            ->assertDontSee('Bu sayfa için içerik hazırlanmaktadır.');

        $order = $this->place($ahmet, $apartment);

        $distanceAcceptance = $this->acceptance($order, LegalConsent::DISTANCE_SALES);
        $preAcceptance = $this->acceptance($order, LegalConsent::PRE_INFORMATION);

        $this->assertSame($distance->id, $distanceAcceptance->legal_document_version_id);
        $this->assertSame('1', $distanceAcceptance->document_version);
        $this->assertSame($distance->body, $distanceAcceptance->legalDocumentVersion->body);

        $this->assertSame($pre->id, $preAcceptance->legal_document_version_id);
        $this->assertSame('1', $preAcceptance->document_version);
        $this->assertSame($pre->body, $preAcceptance->legalDocumentVersion->body);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Kabul edilen metin')
            ->assertSee('MADDE 20 – YÜRÜRLÜK')
            ->assertSee('16. SİPARİŞ ÖNCESİ ÖZET')
            ->assertDontSee('Bu kabul sırasında belge metni saklanmamış.');

        try {
            $distance->fresh()->update(['body' => 'degisti']);
            $this->fail('Yayınlanmış sürüm güncellenebildi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Yayınlanmış yasal belge sürümü değiştirilemez.', $exception->getMessage());
        }

        try {
            $pre->fresh()->delete();
            $this->fail('Yayınlanmış sürüm silinebildi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Yayınlanmış yasal belge sürümü silinemez.', $exception->getMessage());
        }

        $this->assertSame($distance->body, $distance->fresh()->body);
        $this->assertNotNull(LegalDocumentVersion::query()->find($pre->id));
    }

    private function publish(string $key, string $title, string $file): LegalDocumentVersion
    {
        $body = file_get_contents(base_path('tests/Fixtures/legal/'.$file));
        $this->assertNotFalse($body);

        return LegalDocumentVersion::publish($key, '1', $title, $body);
    }

    private function acceptance(UserSubscription $order, string $key): LegalAcceptance
    {
        return LegalAcceptance::query()
            ->where('user_subscription_id', $order->id)
            ->where('document_key', $key)
            ->firstOrFail();
    }

    /**
     * @return array{0: User, 1: Apartment}
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
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        BillingProfile::query()->create([
            'user_id' => $user->id,
            'label' => 'Profil A',
            'party_type' => BillingProfile::TYPE_CORPORATE,
            'legal_name' => 'A Apartmanı Yönetimi',
            'is_active' => true,
        ]);

        return [$user, $apartment];
    }

    private function place(User $user, Apartment $apartment): UserSubscription
    {
        $profile = BillingProfile::query()->where('user_id', $user->id)->where('is_active', true)->firstOrFail();

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
