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

class LegalDocumentVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_acceptance_is_bound_to_the_published_version_and_its_text(): void
    {
        $distance = $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'MESAFELI-SURUM-1-METIN');
        $pre = $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'ONBILGI-SURUM-1-METIN');

        $this->get(route('legal.distance-sales'))
            ->assertOk()
            ->assertSee('Mesafeli Satış 1')
            ->assertSee('MESAFELI-SURUM-1-METIN')
            ->assertDontSee('Bu sayfa için içerik hazırlanmaktadır.');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');

        $this->actingAs($ahmet)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('MESAFELI-SURUM-1-METIN')
            ->assertSee('ONBILGI-SURUM-1-METIN');

        $order = $this->place($ahmet, $apartment);

        $distanceAcceptance = $this->acceptance($order, LegalConsent::DISTANCE_SALES);
        $preAcceptance = $this->acceptance($order, LegalConsent::PRE_INFORMATION);

        $this->assertSame($distance->id, $distanceAcceptance->legal_document_version_id);
        $this->assertSame('1', $distanceAcceptance->document_version);
        $this->assertSame('MESAFELI-SURUM-1-METIN', $distanceAcceptance->legalDocumentVersion->body);
        $this->assertSame(hash('sha256', 'MESAFELI-SURUM-1-METIN'), $distance->content_sha256);

        $this->assertSame($pre->id, $preAcceptance->legal_document_version_id);
        $this->assertSame('1', $preAcceptance->document_version);
        $this->assertSame('ONBILGI-SURUM-1-METIN', $preAcceptance->legalDocumentVersion->body);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Kabul edilen metin')
            ->assertSee('MESAFELI-SURUM-1-METIN')
            ->assertSee('ONBILGI-SURUM-1-METIN')
            ->assertDontSee('Bu kabul sırasında belge metni saklanmamış.');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('MESAFELI-SURUM-1-METIN')
            ->assertSee('ONBILGI-SURUM-1-METIN');
    }

    public function test_a_newer_publication_binds_only_new_acceptances(): void
    {
        $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'MESAFELI-SURUM-1-METIN');
        $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'ONBILGI-SURUM-1-METIN');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $first = $this->place($ahmet, $apartment);
        $this->approve($ahmet, $first);

        $secondDistance = $this->publish(LegalConsent::DISTANCE_SALES, '2', 'Mesafeli Satış 2', 'MESAFELI-SURUM-2-METIN');
        $secondPre = $this->publish(LegalConsent::PRE_INFORMATION, '2', 'Ön Bilgilendirme 2', 'ONBILGI-SURUM-2-METIN');
        $second = $this->place($ahmet, $apartment);

        $firstDistance = $this->acceptance($first, LegalConsent::DISTANCE_SALES);
        $secondDistanceAcceptance = $this->acceptance($second, LegalConsent::DISTANCE_SALES);

        $this->assertSame('1', $firstDistance->document_version);
        $this->assertNotSame($secondDistance->id, $firstDistance->legal_document_version_id);
        $this->assertSame('MESAFELI-SURUM-1-METIN', $firstDistance->legalDocumentVersion->body);

        $this->assertSame($secondDistance->id, $secondDistanceAcceptance->legal_document_version_id);
        $this->assertSame('2', $secondDistanceAcceptance->document_version);
        $this->assertSame($secondPre->id, $this->acceptance($second, LegalConsent::PRE_INFORMATION)->legal_document_version_id);
        $this->assertSame('2', $this->acceptance($second, LegalConsent::PRE_INFORMATION)->document_version);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $first))
            ->assertOk()
            ->assertSee('MESAFELI-SURUM-1-METIN')
            ->assertSee('ONBILGI-SURUM-1-METIN')
            ->assertDontSee('MESAFELI-SURUM-2-METIN')
            ->assertDontSee('ONBILGI-SURUM-2-METIN');

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $second))
            ->assertOk()
            ->assertSee('MESAFELI-SURUM-2-METIN')
            ->assertSee('ONBILGI-SURUM-2-METIN')
            ->assertDontSee('MESAFELI-SURUM-1-METIN')
            ->assertDontSee('ONBILGI-SURUM-1-METIN');
    }

    public function test_a_published_version_cannot_be_changed_or_published_twice(): void
    {
        $version = $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'MESAFELI-SURUM-1-METIN');
        $original = [
            'body' => $version->body,
            'title' => $version->title,
            'document_key' => $version->document_key,
            'version' => $version->version,
            'published_at' => $version->published_at->toDateTimeString(),
        ];

        foreach ([
            'body' => 'DEGISTI',
            'title' => 'Baska baslik',
            'document_key' => LegalConsent::PRIVACY,
            'version' => '9',
            'published_at' => now()->addDay()->toDateTimeString(),
        ] as $attribute => $value) {
            try {
                $version->fresh()->update([$attribute => $value]);
                $this->fail($attribute.' değiştirilebildi.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Yayınlanmış yasal belge sürümü değiştirilemez.', $exception->getMessage());
            }
        }

        $version->refresh();
        $this->assertSame($original['body'], $version->body);
        $this->assertSame($original['title'], $version->title);
        $this->assertSame($original['document_key'], $version->document_key);
        $this->assertSame($original['version'], $version->version);
        $this->assertSame($original['published_at'], $version->published_at->toDateTimeString());

        try {
            $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Tekrar', 'BASKA');
            $this->fail('Aynı sürüm yeniden yayınlanabildi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Bu belge anahtarı ve sürüm numarası zaten yayınlanmış.', $exception->getMessage());
        }

        $this->assertSame(1, LegalDocumentVersion::query()->count());
    }

    public function test_a_published_version_cannot_be_deleted(): void
    {
        $version = $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'MESAFELI-SURUM-1-METIN');

        try {
            $version->delete();
            $this->fail('Yayınlanmış sürüm silinebildi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Yayınlanmış yasal belge sürümü silinemez.', $exception->getMessage());
        }

        $this->assertNotNull(LegalDocumentVersion::query()->find($version->id));
        $this->assertSame('MESAFELI-SURUM-1-METIN', $version->fresh()->body);
    }

    public function test_an_acceptance_without_a_version_is_not_backfilled(): void
    {
        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $order = $this->place($ahmet, $apartment);
        $acceptance = $this->acceptance($order, LegalConsent::DISTANCE_SALES);

        $this->assertNull($acceptance->legal_document_version_id);
        $this->assertSame('1', $acceptance->document_version);

        $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'YENI-METIN-BAGLANMAMALI');
        $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'YENI-ONBILGI-BAGLANMAMALI');

        $acceptance->refresh();
        $this->assertNull($acceptance->legal_document_version_id);
        $this->assertNull($this->acceptance($order, LegalConsent::PRE_INFORMATION)->legal_document_version_id);

        $this->get(route('legal.distance-sales'))
            ->assertOk()
            ->assertSee('YENI-METIN-BAGLANMAMALI');

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('Mesafeli satış sözleşmesi')
            ->assertSee('Bu kabul sırasında belge metni saklanmamış.')
            ->assertDontSee('YENI-METIN-BAGLANMAMALI')
            ->assertDontSee('YENI-ONBILGI-BAGLANMAMALI')
            ->assertDontSee('Kabul edilen metin');
    }

    public function test_another_customer_cannot_see_the_accepted_text(): void
    {
        $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'GIZLI-MESAFELI-METIN');
        $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'GIZLI-ONBILGI-METIN');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $order = $this->place($ahmet, $apartment);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertForbidden()
            ->assertDontSee('GIZLI-MESAFELI-METIN')
            ->assertDontSee('GIZLI-ONBILGI-METIN');
    }

    public function test_cancel_approval_and_rejection_do_not_change_the_accepted_text(): void
    {
        $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'SABIT-MESAFELI-METIN');
        $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'SABIT-ONBILGI-METIN');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $cancelled = $this->place($ahmet, $apartment);
        $cancelledId = $this->acceptance($cancelled, LegalConsent::DISTANCE_SALES)->legal_document_version_id;

        $this->actingAs($ahmet)
            ->post(route('subscriber.subscriptions.cancel', $cancelled))
            ->assertRedirect();

        $cancelled->refresh();
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame($cancelledId, $this->acceptance($cancelled, LegalConsent::DISTANCE_SALES)->legal_document_version_id);
        $this->assertSame('SABIT-MESAFELI-METIN', $this->acceptance($cancelled, LegalConsent::DISTANCE_SALES)->legalDocumentVersion->body);

        $approved = $this->place($ahmet, $apartment);
        $approvedId = $this->acceptance($approved, LegalConsent::DISTANCE_SALES)->legal_document_version_id;
        $this->approve($ahmet, $approved);

        $approved->refresh();
        $this->assertSame(UserSubscription::STATUS_ACTIVE, $approved->status);
        $this->assertSame($approvedId, $this->acceptance($approved, LegalConsent::DISTANCE_SALES)->legal_document_version_id);
        $this->assertSame('SABIT-MESAFELI-METIN', $this->acceptance($approved, LegalConsent::DISTANCE_SALES)->legalDocumentVersion->body);

        $rejected = $this->place($ahmet, $apartment);
        $rejectedId = $this->acceptance($rejected, LegalConsent::PRE_INFORMATION)->legal_document_version_id;
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->patch(route('admin.managers.subscription.reject', [$ahmet, $rejected]))
            ->assertRedirect();

        $rejected->refresh();
        $this->assertSame(UserSubscription::STATUS_CANCELLED, $rejected->status);
        $this->assertSame($rejectedId, $this->acceptance($rejected, LegalConsent::PRE_INFORMATION)->legal_document_version_id);
        $this->assertSame('SABIT-ONBILGI-METIN', $this->acceptance($rejected, LegalConsent::PRE_INFORMATION)->legalDocumentVersion->body);
        $this->assertSame($approvedId, $this->acceptance($approved, LegalConsent::DISTANCE_SALES)->fresh()->legal_document_version_id);
    }

    public function test_changing_the_billing_profile_does_not_change_the_accepted_document(): void
    {
        $version = $this->publish(LegalConsent::DISTANCE_SALES, '1', 'Mesafeli Satış 1', 'FATURADAN-BAGIMSIZ-METIN');
        $this->publish(LegalConsent::PRE_INFORMATION, '1', 'Ön Bilgilendirme 1', 'FATURADAN-BAGIMSIZ-ONBILGI');

        [$ahmet, $apartment] = $this->apartmentFor('Ahmet');
        $profile = BillingProfile::query()->where('user_id', $ahmet->id)->firstOrFail();
        $order = $this->place($ahmet, $apartment);
        $acceptanceId = $this->acceptance($order, LegalConsent::DISTANCE_SALES)->id;

        $profile->update([
            'legal_name' => 'YENI-UNVAN-ZX',
            'label' => 'Yeni etiket',
            'is_active' => false,
        ]);

        $acceptance = LegalAcceptance::query()->findOrFail($acceptanceId);
        $this->assertSame($version->id, $acceptance->legal_document_version_id);
        $this->assertSame('1', $acceptance->document_version);
        $this->assertSame('FATURADAN-BAGIMSIZ-METIN', $acceptance->legalDocumentVersion->body);
        $this->assertSame('A Apartmanı Yönetimi', $order->fresh()->billing_legal_name);

        $this->actingAs($ahmet)
            ->get(route('subscriber.subscriptions.receipt', $order))
            ->assertOk()
            ->assertSee('FATURADAN-BAGIMSIZ-METIN')
            ->assertSee('A Apartmanı Yönetimi')
            ->assertDontSee('YENI-UNVAN-ZX');
    }

    private function publish(string $key, string $version, string $title, string $body): LegalDocumentVersion
    {
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
