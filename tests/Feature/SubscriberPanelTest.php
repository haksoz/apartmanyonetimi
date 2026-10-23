<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_residences_are_cards_under_one_heading(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $this->residence($member, 'Eyup Apartmani', '1');
        $this->residence($member, 'Camlik Apartmani', '4');

        $this->actingAs($member)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Dairelerim')
            ->assertSee('Eyup Apartmani')
            ->assertSee('Camlik Apartmani')
            ->assertSee('Daire 01')
            ->assertSee('Daire 04')
            ->assertSee('Kat Maliki')
            ->assertDontSee('Yönettiğim apartman')
            ->assertDontSee('Yeni Apartman Oluştur')
            ->assertDontSee('sidebar-text">Siparişlerim', false)
            ->assertSee('sidebar-text">Abone Paneli', false)
            ->assertSee('sidebar-text">Hesabım', false);
    }

    public function test_own_order_history_keeps_the_commerce_menu_after_management_ends(): void
    {
        $member = User::factory()->create(['role' => 'user']);

        $this->actingAs($member)
            ->get(route('subscriber.subscriptions.index'))
            ->assertForbidden();

        UserSubscription::factory()->create([
            'user_id' => $member->id,
            'is_active' => false,
            'status' => UserSubscription::STATUS_CANCELLED,
        ]);

        $this->actingAs($member)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('sidebar-text">Siparişlerim', false)
            ->assertSee('sidebar-text">Fatura bilgilerim', false);

        $this->actingAs($member)
            ->get(route('subscriber.subscriptions.index'))
            ->assertOk();
    }

    public function test_a_billing_profile_keeps_the_commerce_menu(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        BillingProfile::query()->create([
            'user_id' => $member->id,
            'label' => 'Eski Fatura',
            'party_type' => BillingProfile::TYPE_INDIVIDUAL,
            'legal_name' => 'Eski Kisi',
            'identity_number' => '11111111111',
            'address' => 'Adres',
            'is_active' => false,
        ]);

        $this->actingAs($member)
            ->get(route('subscriber.billing-profiles.index'))
            ->assertOk()
            ->assertSee('Eski Fatura');
    }

    private function residence(User $member, string $building, string $unitNo): void
    {
        $manager = User::factory()->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => $building,
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => $unitNo,
        ]);
        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'user_id' => $member->id,
            'type' => Account::TYPE_OWNER,
            'name' => $building.' Maliki',
            'is_active' => true,
        ]);
    }
}
