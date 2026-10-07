<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Package;
use App\Models\PriceBand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_reads_active_price_bands_and_drops_package_pricing(): void
    {
        Package::factory()->create([
            'name' => 'Başlangıç',
            'slug' => 'baslangic',
            'monthly_price' => 300,
            'show_on_website' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $band = PriceBand::query()->where('is_quote', false)->orderBy('sort_order')->firstOrFail();
        $band->update([
            'monthly_price' => 1234.5,
            'yearly_price' => 12345,
        ]);

        $owner = User::factory()->create();
        $apartment = Apartment::factory()->create([
            'user_id' => $owner->id,
            'custom_monthly_price' => 99999,
            'custom_yearly_price' => 88888,
        ]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);

        $quote = PriceBand::query()->where('is_quote', true)->where('is_active', true)->firstOrFail();

        $home = $this->get(route('landing'));

        $home->assertOk();
        $home->assertSee('Daire Sakini');
        $home->assertSee(route('register'), false);
        $home->assertSee(route('login'), false);
        $home->assertSee(route('pricing'), false);
        $home->assertSee(route('faq'), false);
        $home->assertSee(route('legal.distance-sales'), false);
        $home->assertDontSee('Başlangıç');
        $home->assertDontSee('Tavsiye Edilen');
        $home->assertDontSee('/register/baslangic', false);
        $home->assertDontSee(route('dashboard'), false);
        $home->assertDontSee('99.999', false);
        $home->assertDontSee('88.888', false);
        $home->assertDontSee('1.234,50');
        $home->assertDontSee('12.345,00');
        $home->assertDontSee($band->label);
        $home->assertDontSee('1–100');
        $home->assertDontSee('101–150');
        $home->assertDontSee('151 ve üzeri');
        $home->assertDontSee('sakin yönetimi');
        $home->assertDontSee('2 ay');
        $home->assertDontSee('deneme');
        $home->assertDontSee('Kredi kartı');
        $home->assertDontSee('kredi kartı');
        $home->assertDontSee('TL/ay');
        $home->assertDontSee('300 TL');
        $home->assertDontSee('900 TL');

        $pricing = $this->get(route('pricing'));

        $pricing->assertOk();
        $pricing->assertSee($band->label);
        $pricing->assertSee('1.234,50 ₺');
        $pricing->assertSee('12.345,00 ₺');
        $pricing->assertSee('101+ daire');
        $pricing->assertSee('Özel Teklif');
        $pricing->assertDontSee($quote->label);
        $pricing->assertSee('1–100 daire');
        $pricing->assertDontSee('101–150 daire ücretsiz açılamaz');
        $pricing->assertDontSee('99.999', false);
        $pricing->assertDontSee('88.888', false);
        $pricing->assertDontSee('Başlangıç');
    }

    public function test_inactive_price_band_is_hidden(): void
    {
        $band = PriceBand::query()->where('is_quote', false)->orderBy('sort_order')->firstOrFail();
        $band->update([
            'label' => 'Gizli Bant 4040',
            'is_active' => false,
        ]);

        $this->get(route('pricing'))
            ->assertOk()
            ->assertDontSee('Gizli Bant 4040');
    }

    public function test_faq_uses_daire_sakini_and_keeps_unit_rules_off_the_homepage(): void
    {
        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Daire Sakini sisteme nasıl eklenir?')
            ->assertSee('1–100 daireli')
            ->assertDontSee('Sakinler sisteme');
    }

    public function test_register_page_no_longer_promises_a_trial(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Ücretsiz Hesabınızı Oluşturun')
            ->assertSee('temel özellikleri ücretsiz kullanmaya başlayın')
            ->assertSee('Üyelik sözleşmesini')
            ->assertSee('Gizlilik ve KVKK aydınlatmasını')
            ->assertSee('name="accept_membership"', false)
            ->assertSee('name="accept_privacy"', false)
            ->assertSee('Okudum, kabul ediyorum')
            ->assertDontSee('Ücretsiz Deneme Başlatın')
            ->assertDontSee('2 ay ücretsiz')
            ->assertDontSee('paketinizi seçin');
    }

    public function test_legal_placeholders_are_public_and_contain_no_bank_details(): void
    {
        $routes = [
            'legal.membership',
            'legal.resident-data',
            'legal.distance-sales',
            'legal.pre-information',
            'legal.privacy',
            'legal.cookies',
            'legal.cancellation',
        ];

        foreach ($routes as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertSee('Bu sayfa için içerik hazırlanmaktadır.')
                ->assertDontSee('IBAN')
                ->assertDontSee('TR00');
        }
    }
}
