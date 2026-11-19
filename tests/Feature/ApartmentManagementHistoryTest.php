<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\ApartmentManagementPeriod;
use App\Models\ManagementOffer;
use App\Models\User;
use App\Support\ApartmentManagementHistory;
use App\Support\CurrentApartment;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApartmentManagementHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_former_manager_without_orders_remains_visible_with_dates_but_has_no_access(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        [$old, $next, $apartment] = $this->building();
        $subscription = \App\Models\Subscription::where('apartment_id', $apartment->id)->sole();
        $start = now()->copy();
        $this->travel(2)->days();
        $this->transfer($old, $next, $apartment);
        $period = ApartmentManagementPeriod::where('user_id', $old->id)->sole();
        $this->assertSame($start->toDateTimeString(), $period->started_at->toDateTimeString());
        $this->assertSame(now()->toDateTimeString(), $period->ended_at->toDateTimeString());
        $this->assertFalse($period->is_current);
        $this->assertSame('2 gün 0 saat', $period->durationLabel());
        $this->assertFalse(app(CurrentApartment::class)->hasAvailableFor($old));
        $this->assertFalse($old->fresh()->managesBuilding());
        $this->assertDatabaseCount('user_subscriptions', 0);
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->get(route('admin.customers.show', $old))->assertOk()
            ->assertSee($apartment->name)->assertSee('Eski yönetici')
            ->assertSee('01.11.2026 – 03.11.2026')->assertDontSee('2 gün 0 saat')
            ->assertDontSee('01.11.2026 03:00')
            ->assertSee($subscription->subscription_no)
            ->assertDontSee(route('admin.apartments.show', $apartment), false)
            ->assertDontSee(route('admin.subscriptions.show', $subscription), false);
        preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', explode('id="customer-apartments"', $response->getContent(), 2)[1], $table);
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $table[1], $cells);
        foreach (array_slice($cells[1], 3, 4) as $cell) {
            $this->assertSame('', trim(strip_tags($cell)));
        }
        $response->assertViewHas('summary', fn ($summary) => $summary['apartments'] === 0 && $summary['active_subscriptions'] === 0);
        $response->assertViewHas('apartmentSubscriptions', fn ($rows) => $rows->count() === 1
            && $rows->first()['role'] === null && ! $rows->first()['can_delete']
            && $rows->first()['is_former_manager']);
        $this->get(route('admin.apartments.show', $apartment))->assertOk()
            ->assertSee('Yönetim geçmişi')->assertSee($old->name)->assertSee($next->name)
            ->assertSee('Devam ediyor')->assertSee('Yönetim başlangıcı:')
            ->assertDontSee('Yöneticilik teklif geçmişi');
    }

    public function test_repeated_terms_are_separate_and_a_non_founder_stays_in_the_customer_list(): void
    {
        [$first, $second, $apartment] = $this->building();
        $third = User::factory()->create(['name' => 'Ucuncu Yonetici']);
        $apartment->members()->attach($third->id, ['role' => 'member', 'is_active' => true]);
        $this->transfer($first, $second, $apartment);
        $this->travel(3)->days();
        $this->transfer($second, $third, $apartment);
        $this->assertFalse($second->ownedApartments()->exists());
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.customers.index', ['search' => $second->email]))->assertOk()->assertSee($second->name);
        $this->get(route('admin.customers.index', ['search' => $second->email, 'filtre' => 'uyeler']))->assertOk()->assertDontSee($second->name);
        $apartment->members()->updateExistingPivot($first->id, ['is_active' => true]);
        $this->travel(1)->days();
        $this->transfer($third, $first, $apartment);
        $terms = ApartmentManagementPeriod::where('user_id', $first->id)->orderBy('id')->get();
        $this->assertCount(2, $terms);
        $this->assertFalse($terms[0]->is_current);
        $this->assertTrue($terms[1]->is_current);
        $this->assertNotEquals($terms[0]->started_at, $terms[1]->started_at);
        $this->assertSame(1, ApartmentManagementPeriod::where('original_apartment_id', $apartment->id)->where('is_current', true)->count());
    }

    public function test_backfill_is_idempotent_and_does_not_invent_member_management_or_unknown_dates(): void
    {
        $old = User::factory()->create(['name' => 'Onceki Yonetici']);
        $next = User::factory()->create(['name' => 'Guncel Yonetici']);
        $resident = User::factory()->create();
        $apartment = Apartment::factory()->forUser($old)->create();
        $apartment->members()->attach($old->id, ['role' => 'member', 'is_active' => false]);
        $apartment->members()->attach($next->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($resident->id, ['role' => 'member', 'is_active' => true]);
        DB::table('apartment_user')->where('apartment_id', $apartment->id)->where('user_id', $old->id)->update(['created_at' => '2026-11-01 08:00:00']);
        $offer = ManagementOffer::create([
            'apartment_id' => $apartment->id, 'user_id' => $next->id, 'offered_by' => $old->id,
            'status' => 'accepted', 'accepted_at' => '2026-11-10 08:00:00',
            'previous_manager_ids' => [$old->id], 'updated_at' => '2026-11-10 08:05:00',
        ]);
        $offer->forceFill(['updated_at' => '2026-11-10 08:05:00'])->save();
        $this->artisan('management-history:backfill')->assertSuccessful();
        $this->artisan('management-history:backfill')->assertSuccessful();
        $this->assertDatabaseCount('apartment_management_periods', 2);
        $period = ApartmentManagementPeriod::where('user_id', $old->id)->sole();
        $this->assertSame('2026-11-01 08:00:00', $period->started_at->toDateTimeString());
        $this->assertSame('2026-11-10 08:05:00', $period->ended_at->toDateTimeString());
        $this->assertFalse($period->is_current);
        $this->assertSame(0, ApartmentManagementPeriod::where('user_id', $resident->id)->count());
        $this->assertFalse(app(CurrentApartment::class)->hasAvailableFor($old));
        $unknown = User::factory()->create();
        $other = Apartment::factory()->create();
        $other->members()->attach($unknown->id, ['role' => 'owner', 'is_active' => true]);
        app(ApartmentManagementHistory::class)->backfill($other);
        $this->assertNull(ApartmentManagementPeriod::where('user_id', $unknown->id)->sole()->started_at);
    }

    public function test_history_survives_apartment_deletion_and_remains_read_only(): void
    {
        [$old, $next, $apartment] = $this->building();
        $this->transfer($old, $next, $apartment);
        $name = $apartment->name;
        $originalId = $apartment->id;
        $apartment->forceDelete();
        $period = ApartmentManagementPeriod::where('user_id', $old->id)->sole();
        $this->assertNull($period->apartment_id);
        $this->assertSame($originalId, $period->original_apartment_id);
        $this->assertSame($name, $period->apartment_name);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.customers.show', $old))->assertOk()
            ->assertSee($name)->assertSee('Eski yönetici')
            ->assertViewHas('apartmentSubscriptions', fn ($rows) => $rows->count() === 1 && ! $rows->first()['can_delete']);
    }

    private function building(): array
    {
        $old = User::factory()->create(['name' => 'Onceki Musteri']);
        $next = User::factory()->create(['name' => 'Sonraki Musteri']);
        $apartment = Apartment::factory()->forUser($old)->create(['unit_count' => 8]);
        $apartment->members()->attach($old->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($next->id, ['role' => 'member', 'is_active' => true]);
        app(ApartmentManagementHistory::class)->startInitial($apartment, $old);
        app(SubscriptionCheckout::class)->openFree($old, $apartment);

        return [$old, $next, $apartment];
    }

    private function transfer(User $old, User $next, Apartment $apartment): void
    {
        $offer = ManagementOffer::create(['apartment_id' => $apartment->id, 'user_id' => $next->id,
            'offered_by' => $old->id, 'status' => 'pending', 'expires_at' => now()->addDay()]);
        $this->actingAs($next)->post(route('management-offers.accept', $offer), ['accept_resident_data' => 1, 'accept_privacy' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('subscriber.dashboard'));
    }
}
