<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataOperationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_a_completed_wipe_with_live_apartment_and_subscription(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $subscriptionNo = Subscription::query()->where('apartment_id', $apartment->id)->value('subscription_no');

        $this->postWipe($owner, $apartment)->assertRedirect();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.data-operations.index'))
            ->assertOk()
            ->assertSee('Veri İşlem Kayıtları')
            ->assertSee('İşlem Verisini Sil')
            ->assertSee('Başarılı')
            ->assertSee('Atlandı. Yedek alınmadı.')
            ->assertSee('Aktif')
            ->assertSee('#'.$apartment->id);

        $operation = ApartmentDataOperation::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('İşlem anındaki abonelik numarası: '.$subscriptionNo)
            ->assertSee('Güncel abonelik kaydı duruyor: '.$subscriptionNo)
            ->assertSee('Güncel abonelik kalemi var')
            ->assertDontSee('Aktif ücretli dönem korunuyor')
            ->assertSee('Arşiv referansı yok')
            ->assertSee((string) $apartment->unit_count);
    }

    public function test_admin_sees_a_completed_setup_renewal(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $before = (int) $apartment->unit_count;

        $this->postRenew($owner, $apartment, 12)->assertRedirect();

        $admin = User::factory()->admin()->create();
        $operation = ApartmentDataOperation::query()->where('action', ApartmentDataOperation::ACTION_RENEW)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Verileri Sil ve Kurulumu Yenile')
            ->assertSee('Başarılı')
            ->assertSee('Atlandı. Yedek alınmadı.')
            ->assertSee((string) $before)
            ->assertSee('güncel daire sayısı 12');
    }

    public function test_a_blocked_renewal_is_shown_as_failed_and_live_state_stays(): void
    {
        [$owner, $apartment] = $this->ownerApartment(10);
        $this->paidCover($owner, $apartment);

        $this->postRenew($owner, $apartment, 4)->assertSessionHasErrors('unit_count');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', ApartmentDataOperation::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Başarısız')
            ->assertSee('Aktif')
            ->assertSee('güncel daire sayısı 10');
    }

    public function test_history_remains_after_the_apartment_record_is_removed(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $this->postWipe($owner, $apartment)->assertRedirect();
        $name = $apartment->name;
        $id = $apartment->id;
        $apartment->forceDelete();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', ApartmentDataOperation::query()->firstOrFail()))
            ->assertOk()
            ->assertSee($name)
            ->assertSee('#'.$id)
            ->assertSee('Kayıt yok');
    }

    public function test_a_manager_cannot_open_the_log(): void
    {
        [$owner] = $this->ownerApartment();

        $this->actingAs($owner)
            ->get(route('admin.data-operations.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function ownerApartment(int $units = 8): array
    {
        $owner = User::factory()->create();
        $apartment = Apartment::factory()->forUser($owner)->create([
            'name' => 'Gözlem Apartmanı',
            'unit_count' => $units,
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        app(\App\Support\SubscriptionCheckout::class)->openFree($owner, $apartment);

        return [$owner, $apartment];
    }

    private function paidCover(User $owner, Apartment $apartment): void
    {
        $order = \App\Models\UserSubscription::factory()->create([
            'user_id' => $owner->id,
            'status' => \App\Models\UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
        \App\Models\SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_min_units' => 1,
            'band_max_units' => 14,
            'band_label' => '1–14',
            'amount' => 100,
            'currency' => 'TRY',
            'plan' => \App\Models\SubscriptionItem::PLAN_PAID,
            'status' => \App\Models\SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
    }

    private function postWipe(User $owner, Apartment $apartment)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.destroy-all', $apartment), [
                'current_password' => 'password',
                'confirmation' => 'tüm verilerin silinmesini kabul ediyorum',
                'skip_archive_check' => '1',
            ]);
    }

    private function postRenew(User $owner, Apartment $apartment, int $units)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.renew-setup', $apartment), [
                'current_password' => 'password',
                'unit_count' => $units,
                'confirmation' => 'kurulumun yenilenmesini kabul ediyorum',
                'skip_archive_check' => '1',
            ]);
    }
}
