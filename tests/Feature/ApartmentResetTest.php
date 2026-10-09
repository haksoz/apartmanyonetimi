<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Due;
use App\Models\QuoteRequest;
use App\Models\SubscriptionItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentReset\ApartmentResetArchive;
use App\Support\CurrentApartment;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_wipe_keeps_the_apartment_accounts_and_paid_period(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $order = $this->paidPeriod($owner, $apartment, 10, 14, now()->addYear());
        $account = $this->account($apartment);
        $due = $this->due($apartment, $account);

        $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee('İşlem verisini sil')
            ->assertSee('Verileri sil ve kurulumu yenile')
            ->assertSee('Yedekleme altyapısı kontrolünü atla')
            ->assertSee('Bu işlemin öncesinde verilerin yedeği alınmayacaktır.')
            ->assertDontSee('Apartmanı Sil ve Yenile')
            ->assertDontSee('30 gün');

        $this->postWipe($owner, $apartment)->assertRedirect();

        $this->assertDatabaseHas('apartment_data_operations', [
            'apartment_id' => $apartment->id,
            'action' => ApartmentDataOperation::ACTION_WIPE,
            'result' => ApartmentDataOperation::RESULT_COMPLETED,
            'archive_status' => ApartmentDataOperation::ARCHIVE_BYPASSED,
        ]);
        $this->assertSame(10, $apartment->fresh()->unit_count);
        $this->assertNotNull(Account::query()->find($account->id));
        $this->assertSoftDeleted($due);
        $this->assertSame($order->price, $order->fresh()->price);
        $this->assertTrue(SubscriptionItem::query()->where('apartment_id', $apartment->id)->covering()->exists());

        [$freeOwner, $freeApartment] = $this->ownerApartment(8);
        $freeAccount = $this->account($freeApartment);
        $freeDue = $this->due($freeApartment, $freeAccount);
        $this->postWipe($freeOwner, $freeApartment)->assertRedirect();
        $this->assertSame(8, $freeApartment->fresh()->unit_count);
        $this->assertNotNull(Account::query()->find($freeAccount->id));
        $this->assertSoftDeleted($freeDue);
    }

    public function test_never_paid_setup_renewal_stays_on_the_same_apartment_until_101(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $orderCount = UserSubscription::query()->count();

        $this->postRenew($owner, $apartment, 40, false, false)->assertSessionHasErrors('skip_archive_check');
        $this->assertSame(10, $apartment->fresh()->unit_count);

        $this->postRenew($owner, $apartment, 40)->assertRedirect(route('apartments.wizard.cash-box', $apartment));

        $this->assertSame(40, $apartment->fresh()->unit_count);
        $this->assertSame(1, Apartment::query()->count());
        $this->assertNull($apartment->fresh()->setup_completed_at);
        $this->assertSame($orderCount, UserSubscription::query()->count());

        $this->postRenew($owner, $apartment, 120)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(40, $apartment->fresh()->unit_count);
        $this->assertSame(1, QuoteRequest::query()->where('unit_count', 120)->count());
        $this->assertSame(1, Apartment::query()->count());
    }

    public function test_active_paid_period_can_move_within_the_purchased_band(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $order = $this->paidPeriod($owner, $apartment, 10, 14, now()->addYear());
        $item = SubscriptionItem::query()->where('subscription_id', $order->id)->firstOrFail();

        $this->postRenew($owner, $apartment, 12)->assertRedirect(route('apartments.wizard.cash-box', $apartment));

        $this->assertSame(12, $apartment->fresh()->unit_count);
        $this->assertSame(10, $item->fresh()->unit_count);
        $this->assertSame($order->id, $item->fresh()->subscription_id);
        $this->assertSame($order->price, $order->fresh()->price);
        $this->assertTrue($item->fresh()->isCovering());
    }

    public function test_active_paid_period_cannot_reduce_the_purchased_unit_count(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $order = $this->paidPeriod($owner, $apartment, 10, 14, now()->addYear());

        $this->postRenew($owner, $apartment, 8)
            ->assertRedirect()
            ->assertSessionHasErrors('unit_count');

        $this->assertSame(10, $apartment->fresh()->unit_count);
        $this->assertSame($order->price, $order->fresh()->price);
        $this->assertSame(1, Apartment::query()->count());
    }

    public function test_expired_paid_period_can_shrink_inside_the_last_band(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $order = $this->paidPeriod($owner, $apartment, 10, 14, now()->subDay());

        $this->postRenew($owner, $apartment, 8)->assertRedirect(route('apartments.wizard.cash-box', $apartment));

        $this->assertSame(8, $apartment->fresh()->unit_count);
        $this->assertSame(10, SubscriptionItem::query()->where('subscription_id', $order->id)->first()->unit_count);
        $this->assertFalse(SubscriptionItem::query()->where('apartment_id', $apartment->id)->covering()->exists());
        $this->assertSame($order->id, UserSubscription::query()->first()->id);

        [$owner, $apartment] = $this->ownerApartment(20);
        $this->paidPeriod($owner, $apartment, 20, 32, now()->subDay(), 15);
        $this->postRenew($owner, $apartment, 10)->assertSessionHasErrors('unit_count');
        $this->assertSame(20, $apartment->fresh()->unit_count);
    }

    public function test_exceeding_the_paid_band_opens_a_separate_free_apartment_after_confirmation(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $order = $this->paidPeriod($owner, $apartment, 10, 14, now()->addYear());
        $due = $this->due($apartment, $this->account($apartment));

        $this->postRenew($owner, $apartment, 20)
            ->assertRedirect()
            ->assertSessionHasErrors('accept_new_free_apartment');

        $this->assertSame(1, Apartment::query()->count());
        $this->assertNotNull(Due::query()->find($due->id));

        $this->postRenew($owner, $apartment, 20, true)
            ->assertRedirect();

        $created = Apartment::query()->where('id', '!=', $apartment->id)->firstOrFail();
        $this->assertSame(20, $created->unit_count);
        $this->assertSame(10, $apartment->fresh()->unit_count);
        $this->assertSame($order->id, SubscriptionItem::query()->where('apartment_id', $apartment->id)->where('plan', SubscriptionItem::PLAN_PAID)->first()->subscription_id);
        $this->assertNull(SubscriptionItem::query()->where('apartment_id', $created->id)->where('plan', SubscriptionItem::PLAN_PAID)->first());
        $this->assertTrue(SubscriptionItem::query()->where('apartment_id', $created->id)->where('plan', SubscriptionItem::PLAN_FREE)->exists());
        $this->assertNotNull(Due::query()->find($due->id));
        $this->assertSame($order->price, $order->fresh()->price);
    }

    public function test_one_hundred_one_units_always_goes_to_a_quote(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $this->paidPeriod($owner, $apartment, 10, 14, now()->subDay());

        $this->postRenew($owner, $apartment, 101)->assertRedirect()->assertSessionHas('status');

        $this->assertSame(1, Apartment::query()->count());
        $this->assertSame(10, $apartment->fresh()->unit_count);
        $this->assertSame(1, QuoteRequest::query()->where('unit_count', 101)->count());
    }

    public function test_a_stranger_cannot_wipe_another_apartment(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment(10);
        $account = $this->account($apartment);
        $due = $this->due($apartment, $account);
        $stranger = User::factory()->create();
        $other = Apartment::factory()->forUser($stranger)->create(['name' => 'Başka', 'unit_count' => 4, 'is_active' => true]);
        $other->members()->attach($stranger->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($stranger)
            ->withSession([CurrentApartment::SESSION_KEY => $other->id])
            ->post(route('apartments.destroy-all', $apartment), [
                'current_password' => 'password',
                'confirmation' => 'tüm verilerin silinmesini kabul ediyorum',
            ])
            ->assertNotFound();

        $this->assertNotNull(Due::query()->find($due->id));
        $this->assertSame($owner->id, $apartment->fresh()->user_id);
    }

    public function test_delete_does_not_start_when_archiving_fails(): void
    {
        [$owner, $apartment] = $this->ownerApartment(10);
        $due = $this->due($apartment, $this->account($apartment));

        $this->postWipe($owner, $apartment, false)
            ->assertRedirect()
            ->assertSessionHasErrors('skip_archive_check');

        $this->assertNotNull(Due::query()->find($due->id));
        $this->assertSame(10, $apartment->fresh()->unit_count);
        $this->assertDatabaseMissing('apartment_data_operations', [
            'apartment_id' => $apartment->id,
            'result' => ApartmentDataOperation::RESULT_COMPLETED,
        ]);
    }

    private function acceptArchive(): void
    {
        $this->app->bind(ApartmentResetArchive::class, fn () => new class implements ApartmentResetArchive
        {
            public function capture(ApartmentDataOperation $operation): bool
            {
                return true;
            }
        });
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function ownerApartment(int $units): array
    {
        $owner = User::factory()->create();
        $apartment = Apartment::factory()->forUser($owner)->create([
            'name' => 'Deneme Apartmanı',
            'unit_count' => $units,
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($owner, $apartment);

        return [$owner, $apartment];
    }

    private function paidPeriod(User $owner, Apartment $apartment, int $units, int $bandMax, $expiresAt, int $bandMin = 1): UserSubscription
    {
        $order = UserSubscription::factory()->create([
            'user_id' => $owner->id,
            'price' => 1500,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addYear(),
        ]);

        SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => $units,
            'band_label' => '1–14 daire',
            'band_min_units' => $bandMin,
            'band_max_units' => $bandMax,
            'amount' => 1500,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subMonth(),
            'expires_at' => $expiresAt,
        ]);

        return $order;
    }

    private function account(Apartment $apartment): Account
    {
        $unit = Unit::query()->create([
            'apartment_id' => $apartment->id,
            'unit_no' => '01',
        ]);

        return Account::query()->create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => '01. Daire Kat Maliki',
            'account_opening_date' => now()->toDateString(),
        ]);
    }

    private function due(Apartment $apartment, Account $account): Due
    {
        return Due::query()->create([
            'apartment_id' => $apartment->id,
            'unit_id' => $account->unit_id,
            'account_id' => $account->id,
            'period' => '2026-01',
            'amount' => 100,
            'remaining_amount' => 100,
            'due_date' => '2026-01-31',
            'status' => 'unpaid',
        ]);
    }

    private function postWipe(User $owner, Apartment $apartment, bool $skipArchive = true)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.destroy-all', $apartment), [
                'current_password' => 'password',
                'confirmation' => 'tüm verilerin silinmesini kabul ediyorum',
                'skip_archive_check' => $skipArchive ? '1' : '0',
            ]);
    }

    private function postRenew(User $owner, Apartment $apartment, int $units, bool $acceptNew = false, bool $skipArchive = true)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.renew-setup', $apartment), [
                'current_password' => 'password',
                'unit_count' => $units,
                'confirmation' => 'kurulumun yenilenmesini kabul ediyorum',
                'accept_new_free_apartment' => $acceptNew ? '1' : '0',
                'skip_archive_check' => $skipArchive ? '1' : '0',
            ]);
    }
}
