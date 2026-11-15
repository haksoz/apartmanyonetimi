<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-15 12:00:00', 'Europe/Istanbul'));
        // Existing dashboard charts use MySQL's year-month formatter.
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\DB::connection()->getPdo()->sqliteCreateFunction(
                'DATE_FORMAT', fn ($date, $format) => $date ? substr($date, 0, 7) : null, 2,
            );
        }
    }

    private function apartment(User $user, array $data = []): Apartment
    {
        $apartment = Apartment::factory()->forUser($user)->create($data + [
            'name' => 'Barış Apartmanı', 'is_active' => true, 'unit_count' => 10,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($user, $apartment);
        return $apartment;
    }

    private function paid(User $user, Apartment $apartment, int $days, array $item = [], array $header = []): SubscriptionItem
    {
        $order = UserSubscription::factory()->create($header + ['user_id' => $user->id]);
        return $apartment->subscriptionItems()->create($item + [
            'subscription_id' => $order->id, 'apartment_name' => $apartment->name,
            'unit_count' => 10, 'band_label' => 'Test', 'band_min_units' => 1,
            'amount' => 150, 'currency' => 'TRY', 'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE, 'started_at' => now()->subMonth(),
            'expires_at' => now()->addDays($days),
        ]);
    }

    private function dashboard(User $user, Apartment $apartment): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('dashboard'))->assertOk();
    }

    public function test_single_quota_paid_manager_logs_into_dashboard_but_can_still_open_subscriber_panel(): void
    {
        $user = User::factory()->withApartmentQuota(1)->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 15);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($apartment->id, session(CurrentApartment::SESSION_KEY));
        $this->get(route('subscriber.dashboard'))->assertOk();
    }

    public function test_multiple_managed_apartments_do_not_auto_open_even_with_an_old_selection(): void
    {
        $user = User::factory()->withApartmentQuota(2)->create();
        $first = $this->apartment($user);
        $second = $this->apartment($user, ['name' => 'Diğer Apartman']);
        $this->paid($user, $first, 15);
        $this->paid($user, $second, 3);
        $this->withSession([CurrentApartment::SESSION_KEY => $second->id])->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('subscriber.dashboard'));
        $this->dashboard($user, $first)->assertSee('15 gün kaldı')->assertDontSee('3 gün kaldı');
        $this->dashboard($user, $second)->assertSee('3 gün kaldı')->assertSee('Süreyi uzat')->assertDontSee('15 gün kaldı');
    }

    public function test_single_free_or_expired_apartment_opens_dashboard_but_incomplete_setup_stays_in_panel(): void
    {
        foreach (['free', 'expired', 'incomplete'] as $state) {
            $user = User::factory()->withApartmentQuota(1)->create();
            $apartment = $this->apartment($user, $state === 'incomplete' ? ['setup_completed_at' => null] : []);
            if ($state !== 'free') {
                $this->paid($user, $apartment, $state === 'expired' ? -1 : 15);
            }
            $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($state === 'incomplete' ? 'subscriber.dashboard' : 'dashboard'));
            if ($state !== 'incomplete') {
                $this->assertSame($apartment->id, session(CurrentApartment::SESSION_KEY));
                $this->get(route('subscriber.dashboard'))->assertOk();
            }
            $this->post(route('logout'));
        }
    }

    public function test_normal_paid_summary_shows_date_and_days_without_a_renewal_prompt(): void
    {
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 15);
        $this->dashboard($user, $apartment)->assertSee('AidatCep Ücretli Kullanım')->assertSee('30.11.2026')
            ->assertSee('15 gün kaldı')->assertSee('Abonelik detayları')->assertDontSee('Süreyi uzat')
            ->assertDontSee('Ücretli Kullanıma Geç');
    }

    public function test_actual_apartment_count_determines_auto_entry_rather_than_quota(): void
    {
        foreach ([2, 1] as $limit) {
            $user = User::factory()->withApartmentQuota($limit)->create();
            $apartment = $this->apartment($user);
            $this->paid($user, $apartment, 15);
            if ($limit === 1) {
                // A later quota reduction must not cause an arbitrary apartment selection.
                $this->apartment($user);
            }
            $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($limit === 1 ? 'subscriber.dashboard' : 'dashboard'));
            $this->post(route('logout'));
        }
    }

    public function test_countdown_uses_istanbul_calendar_days_at_midnight_boundaries(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-15 23:30:00', 'Europe/Istanbul'));
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 1, [
            'expires_at' => \Illuminate\Support\Carbon::parse('2026-11-16 00:30:00', 'Europe/Istanbul')->utc(),
        ]);
        $this->dashboard($user, $apartment)->assertSee('16.11.2026')->assertSee('1 gün kaldı');
    }

    public function test_last_five_days_and_expiry_day_show_renewal_but_six_days_does_not(): void
    {
        $user = User::factory()->create();
        foreach ([6, 5, 1, 0] as $days) {
            $apartment = $this->apartment($user);
            $this->paid($user, $apartment, $days);
            $response = $this->dashboard($user, $apartment);
            if ($days <= 5) {
                $response->assertSee('Süreyi uzat')->assertSee($days === 0 ? 'Bugün sona eriyor' : $days.' gün kaldı');
            } else {
                $response->assertDontSee('Süreyi uzat');
            }
        }
    }

    public function test_prepaid_contiguous_renewal_extends_the_countdown_and_a_gap_does_not(): void
    {
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 3);
        $this->paid($user, $apartment, 33, ['started_at' => now()->addDays(3)]);
        $this->dashboard($user, $apartment)->assertSee('33 gün kaldı')->assertDontSee('Süreyi uzat');

        $gapped = $this->apartment($user);
        $this->paid($user, $gapped, 3);
        $this->paid($user, $gapped, 33, ['started_at' => now()->addDays(5)]);
        $this->dashboard($user, $gapped)->assertSee('3 gün kaldı')->assertSee('Süreyi uzat');
    }

    public function test_pending_renewal_links_to_own_order_and_suppresses_a_second_renewal(): void
    {
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 3);
        $pending = $this->paid($user, $apartment, 33, ['status' => 'pending'], ['status' => 'pending', 'is_active' => false]);
        $this->dashboard($user, $apartment)->assertSee('3 gün kaldı')->assertSee('ödeme / onay bekliyor')
            ->assertSee(route('subscriber.subscriptions.receipt', $pending->subscription_id), false)
            ->assertDontSee('Süreyi uzat');
    }

    public function test_pending_order_of_another_payer_does_not_link_to_their_receipt(): void
    {
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $other = User::factory()->create();
        $pending = $this->paid($other, $apartment, 33, ['status' => 'pending'], ['status' => 'pending', 'is_active' => false]);
        $this->dashboard($user, $apartment)->assertSee('ödeme / onay bekliyor')
            ->assertDontSee(route('subscriber.subscriptions.receipt', $pending->subscription_id), false);
    }

    public function test_free_and_expired_summaries_offer_upgrade_or_renewal(): void
    {
        $user = User::factory()->create();
        $free = $this->apartment($user);
        $this->dashboard($user, $free)->assertSee('AidatCep Ücretsiz Kullanım')->assertSee('Aktif')
            ->assertDontSee('tracking-wide text-slate-400">Özet</p>', false)
            ->assertSee('AidatCep Ücretli Kullanım avantajlarından yararlanın.')
            ->assertSee('Aidatların otomatik oluşturulması')
            ->assertSee('Giderin dairelere dağıtılması')
            ->assertSee('Metrekare ve paya göre dağıtım')
            ->assertSee('Gider belgesi yükleme ve rapor dışa aktarma')
            ->assertSee('Ücretli Kullanıma Geç')
            ->assertSee(route('subscriber.subscriptions.create', ['apartment_id' => $free->id]), false);
        $expired = $this->apartment($user);
        $this->paid($user, $expired, -1);
        $this->dashboard($user, $expired)->assertSee('Ücretli kullanım süreniz doldu')->assertSee('Ücretli kullanımı yenile');
    }

    public function test_unlimited_paid_period_has_no_countdown(): void
    {
        $user = User::factory()->create();
        $apartment = $this->apartment($user);
        $this->paid($user, $apartment, 3, ['expires_at' => null]);
        $this->dashboard($user, $apartment)->assertSee('Süresiz')->assertDontSee('gün kaldı')->assertDontSee('Süreyi uzat');
    }

    public function test_renewal_screen_only_preselects_an_eligible_owned_apartment(): void
    {
        $this->seed(\Database\Seeders\DefaultPackagesSeeder::class);
        $user = User::factory()->create();
        $first = $this->apartment($user);
        $second = $this->apartment($user);
        $this->actingAs($user)->get(route('subscriber.subscriptions.create', ['apartment_id' => $second->id]))
            ->assertOk()->assertViewHas('selectedApartmentId', $second->id);
        $foreign = $this->apartment(User::factory()->create());
        $this->get(route('subscriber.subscriptions.create', ['apartment_id' => $foreign->id]))
            ->assertOk()->assertViewHas('selectedApartmentId', null);
    }
}
