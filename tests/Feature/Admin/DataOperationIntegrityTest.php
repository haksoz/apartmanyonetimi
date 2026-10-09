<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Due;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentReset\ApartmentOperationalData;
use App\Support\ApartmentReset\ApartmentReset;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DataOperationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApartmentReset::$beforeFinish = null;
        ApartmentDataOperation::flushEventListeners();
        parent::tearDown();
    }

    public function test_deleting_the_actor_keeps_the_operation_and_the_recorded_name(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $name = $owner->name;
        $this->postWipe($owner, $apartment)->assertRedirect();

        $owner->delete();

        $operation = ApartmentDataOperation::query()->firstOrFail();
        $this->assertNull($operation->user_id);
        $this->assertSame($name, $operation->actor_name);
    }

    public function test_a_new_free_apartment_rolls_back_when_the_operation_log_cannot_be_written(): void
    {
        [$owner, $apartment] = $this->ownerApartment(10);
        $this->paidCover($owner, $apartment);
        $orders = UserSubscription::query()->count();

        ApartmentDataOperation::creating(function (ApartmentDataOperation $operation) {
            if ($operation->action === ApartmentDataOperation::ACTION_NEW_APARTMENT
                && $operation->result === ApartmentDataOperation::RESULT_COMPLETED) {
                throw new RuntimeException('log failed');
            }
        });

        try {
            $this->postRenew($owner, $apartment, 20, true);
        } catch (RuntimeException $exception) {
            $this->assertSame('log failed', $exception->getMessage());
        }

        $this->assertSame(1, Apartment::query()->count());
        $this->assertSame($orders, UserSubscription::query()->count());
        $this->assertSame($apartment->id, Subscription::query()->value('apartment_id'));
        $this->assertDatabaseMissing('apartment_data_operations', [
            'action' => ApartmentDataOperation::ACTION_NEW_APARTMENT,
            'result' => ApartmentDataOperation::RESULT_COMPLETED,
        ]);
    }

    public function test_live_subscription_labels_distinguish_the_record_the_items_and_the_paid_period(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $this->postWipe($owner, $apartment)->assertRedirect();
        $operation = ApartmentDataOperation::query()->firstOrFail();
        $admin = User::factory()->admin()->create();

        SubscriptionItem::query()->where('apartment_id', $apartment->id)->delete();

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Abonelik kaydı var; abonelik kalemi bulunamadı')
            ->assertSee('Güncel ücretli dönem yok')
            ->assertDontSee('Aktif ücretli dönem korunuyor');

        SubscriptionItem::query()->create([
            'apartment_id' => $apartment->id,
            'apartment_subscription_id' => Subscription::query()->where('apartment_id', $apartment->id)->value('id'),
            'apartment_name' => $apartment->name,
            'unit_count' => 8,
            'band_min_units' => 1,
            'band_label' => 'Ücretsiz',
            'amount' => 0,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_FREE,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Güncel abonelik kalemi var: 1')
            ->assertSee('Güncel ücretli dönem yok')
            ->assertDontSee('Aktif ücretli dönem korunuyor');

        $order = UserSubscription::factory()->create([
            'user_id' => $owner->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
        SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 8,
            'band_min_units' => 1,
            'band_max_units' => 14,
            'band_label' => '1–14',
            'amount' => 100,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Güncel ücretli dönem aktif değil')
            ->assertDontSee('Aktif ücretli dönem korunuyor');
    }

    public function test_an_active_paid_period_must_belong_to_the_apartments_subscription(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $this->postWipe($owner, $apartment)->assertRedirect();
        $operation = ApartmentDataOperation::query()->firstOrFail();
        $admin = User::factory()->admin()->create();
        $subscriptionId = Subscription::query()->where('apartment_id', $apartment->id)->value('id');
        $order = UserSubscription::factory()->create([
            'user_id' => $owner->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $item = SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_subscription_id' => $subscriptionId,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 8,
            'band_min_units' => 1,
            'band_max_units' => 14,
            'band_label' => '1–14',
            'amount' => 100,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Güncel ücretli dönem aktif');

        $other = Apartment::factory()->forUser($owner)->create(['name' => 'Başka Apartman', 'unit_count' => 4, 'is_active' => true]);
        $otherSubscription = Subscription::query()->create([
            'apartment_id' => $other->id,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
        ]);
        $item->update(['apartment_subscription_id' => $otherSubscription->id]);

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Güncel ücretli dönem aktif değil')
            ->assertDontSee('Güncel ücretli dönem aktif</dd>');

        Subscription::query()->whereKey($subscriptionId)->delete();

        $this->actingAs($admin)
            ->get(route('admin.data-operations.show', $operation))
            ->assertOk()
            ->assertSee('Güncel ücretli dönem yok')
            ->assertDontSee('Güncel ücretli dönem aktif</dd>');
    }

    public function test_a_wipe_error_rolls_back_the_data_and_marks_the_operation_failed(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $due = $this->due($apartment);

        $this->app->bind(ApartmentOperationalData::class, function () {
            return new class extends ApartmentOperationalData
            {
                public function wipe(Apartment $apartment): void
                {
                    Due::query()->where('apartment_id', $apartment->id)->delete();

                    throw new RuntimeException('wipe failed');
                }
            };
        });

        try {
            $this->postWipe($owner, $apartment);
        } catch (RuntimeException $exception) {
            $this->assertSame('wipe failed', $exception->getMessage());
        }

        $this->assertNotNull(Due::query()->find($due->id));
        $this->assertSame(
            ApartmentDataOperation::RESULT_FAILED,
            ApartmentDataOperation::query()->firstOrFail()->result,
        );
    }

    public function test_an_error_before_finish_marks_the_operation_failed(): void
    {
        [$owner, $apartment] = $this->ownerApartment();
        $due = $this->due($apartment);

        ApartmentReset::$beforeFinish = function (): void {
            throw new RuntimeException('before finish');
        };

        try {
            $this->postWipe($owner, $apartment);
        } catch (RuntimeException $exception) {
            $this->assertSame('before finish', $exception->getMessage());
        }

        $operation = ApartmentDataOperation::query()->firstOrFail();
        $this->assertSame(ApartmentDataOperation::RESULT_FAILED, $operation->result);
        $this->assertNotSame(ApartmentDataOperation::RESULT_COMPLETED, $operation->result);
        $this->assertNull(Due::query()->find($due->id));
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function ownerApartment(int $units = 8): array
    {
        $owner = User::factory()->create(['name' => 'İşlemi Yapan']);
        $apartment = Apartment::factory()->forUser($owner)->create([
            'name' => 'Gözlem Apartmanı',
            'unit_count' => $units,
            'is_active' => true,
        ]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        app(\App\Support\SubscriptionCheckout::class)->openFree($owner, $apartment);

        return [$owner, $apartment];
    }

    private function paidCover(User $owner, Apartment $apartment): void
    {
        $order = UserSubscription::factory()->create([
            'user_id' => $owner->id,
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
        SubscriptionItem::query()->create([
            'subscription_id' => $order->id,
            'apartment_id' => $apartment->id,
            'apartment_name' => $apartment->name,
            'unit_count' => 10,
            'band_min_units' => 1,
            'band_max_units' => 14,
            'band_label' => '1–14',
            'amount' => 100,
            'currency' => 'TRY',
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
    }

    private function due(Apartment $apartment): Due
    {
        return Due::query()->create([
            'apartment_id' => $apartment->id,
            'period' => '2026-01',
            'amount' => 100,
            'remaining_amount' => 100,
            'due_date' => '2026-01-31',
            'status' => 'unpaid',
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

    private function postRenew(User $owner, Apartment $apartment, int $units, bool $acceptNew = false)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.renew-setup', $apartment), [
                'current_password' => 'password',
                'unit_count' => $units,
                'confirmation' => 'kurulumun yenilenmesini kabul ediyorum',
                'accept_new_free_apartment' => $acceptNew ? '1' : '0',
                'skip_archive_check' => '1',
            ]);
    }
}
