<?php

namespace Tests\Feature;

use App\Models\PriceBand;
use App\Models\PriceCampaign;
use App\Models\User;
use App\Support\ApartmentCommercial;
use App\Support\PriceQuote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerUnitPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_band_boundaries_and_per_unit_totals_with_campaign_and_tax(): void
    {
        foreach ([1 => 1, 14 => 1, 15 => 15, 28 => 15, 29 => 29, 50 => 29, 51 => 51, 100 => 51, 101 => 101, 500 => 101] as $count => $min) {
            $this->assertSame($min, app(PriceQuote::class)->bandFor($count)->min_units);
        }
        $band = PriceBand::where('per_unit', true)->firstOrFail();
        $band->update(['monthly_price' => 10, 'yearly_price' => 100]);
        PriceCampaign::create(['name' => 'Discount', 'price_band_id' => $band->id, 'percent_off' => 10, 'amount_off' => 5, 'is_active' => true]);
        $quote = app(PriceQuote::class)->forUnits(120);
        $this->assertFalse($quote['requires_quote']);
        $this->assertSame(1075.0, $quote['net_amount']);
        $this->assertSame(1290.0, $quote['amount']);
        $this->assertSame(10795.0, app(PriceQuote::class)->forUnits(120, 'yearly')['net_amount']);
        $this->assertFalse(app(ApartmentCommercial::class)->assess(120)['quote']);
    }

    public function test_missing_rate_blocks_checkout_instead_of_becoming_zero(): void
    {
        $quote = app(PriceQuote::class)->forUnits(101);
        $this->assertNull($quote['amount']);
        $this->assertTrue($quote['requires_quote']);
    }

    public function test_admin_rate_is_shown_on_public_page_and_used_in_quotes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $band = PriceBand::where('per_unit', true)->firstOrFail();
        $this->actingAs($admin)->patch(route('admin.commercial.bands.update', $band), [
            'monthly_price' => 12.5, 'yearly_price' => 125, 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('pricing'))->assertOk()->assertSee('12,50 ₺ / daire')->assertDontSee('Özel Teklif');
        $this->assertSame(1500.0, app(PriceQuote::class)->forUnits(120)['net_amount']);
        $this->actingAs($admin)->patch(route('admin.commercial.bands.update', $band), [
            'monthly_price' => null, 'yearly_price' => 125, 'is_active' => 1,
        ])->assertSessionHasErrors('monthly_price');
    }

    public function test_inline_save_returns_confirmation_without_a_global_flash(): void
    {
        $admin = User::factory()->admin()->create();
        $band = PriceBand::where('per_unit', true)->firstOrFail();
        $this->actingAs($admin)->patchJson(route('admin.commercial.bands.update', $band), [
            'monthly_price' => 15, 'yearly_price' => 150, 'is_active' => true,
        ])->assertOk()->assertJsonPath('message', 'Kaydedildi')->assertSessionMissing('status');
        $this->assertEquals(15, $band->fresh()->monthly_price);

        $this->patchJson(route('admin.commercial.bands.update', $band), [
            'monthly_price' => -1, 'yearly_price' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors('monthly_price');
        $this->assertEquals(15, $band->fresh()->monthly_price);
    }
}
