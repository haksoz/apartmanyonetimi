<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\CheckoutJourney;
use App\Models\User;
use App\Support\CheckoutJourneyTracking;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutJourneyReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function journey(): CheckoutJourney
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create();

        return app(CheckoutJourneyTracking::class)->begin($user, $apartment);
    }

    public function test_new_reference_is_persisted_and_does_not_change_after_progress_or_year_changes(): void
    {
        $this->travelTo('2026-12-31 12:00:00');
        $journey = $this->journey();
        $number = $journey->reference;
        $this->assertMatchesRegularExpression('/^GCS-26-\d{6}$/', $number);
        $this->assertSame($number, $journey->getRawOriginal('reference_number'));
        $journey->update(['stage' => 'billing', 'closed_at' => now()]);
        $this->travelTo('2027-01-01 12:00:00');
        $this->assertSame($number, $journey->fresh()->reference);
        $this->assertMatchesRegularExpression('/^GCS-27-\d{6}$/', $this->journey()->reference);
    }

    public function test_migration_preserves_old_references_and_they_remain_searchable(): void
    {
        $journey = $this->journey();
        $legacy = 'GCS-'.str_pad((string) $journey->id, 6, '0', STR_PAD_LEFT);
        $created = $journey->created_at;
        $migration = require database_path('migrations/2026_11_20_130000_add_reference_number_to_checkout_journeys.php');
        $migration->down();
        $migration->up();
        $this->assertSame($legacy, $journey->fresh()->reference);
        $this->assertSame($legacy, $journey->fresh()->reference_number);
        $this->assertTrue($created->equalTo($journey->fresh()->created_at));
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.checkout-journeys.index', ['search' => $legacy]))->assertOk()
            ->assertViewHas('journeys', fn ($rows) => $rows->total() === 1)->assertSee($legacy);
    }

    public function test_new_reference_search_does_not_confuse_database_id_with_public_reference(): void
    {
        $journey = $this->journey();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.checkout-journeys.index', ['search' => strtolower($journey->reference)]))->assertOk()
            ->assertViewHas('journeys', fn ($rows) => $rows->total() === 1);
        $legacyAlias = 'GCS-'.str_pad((string) $journey->id, 6, '0', STR_PAD_LEFT);
        $this->get(route('admin.checkout-journeys.index', ['search' => $legacyAlias]))->assertOk()
            ->assertViewHas('journeys', fn ($rows) => $rows->total() === 0);
    }

    public function test_database_rejects_duplicate_reference_numbers(): void
    {
        $journey = $this->journey();
        $this->expectException(QueryException::class);
        DB::table('checkout_journeys')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'reference_number' => $journey->reference,
            'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
