<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Package;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentWizardTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithApartment(): array
    {
        $package = Package::factory()->create(['apartment_limit' => 5]);
        $user = User::factory()->withSubscription($package)->create();
        $apartment = Apartment::factory()->forUser($user)->create();
        $user->apartments()->attach($apartment->id, ['role' => 'owner', 'is_active' => true]);

        return [$user, $apartment];
    }

    public function test_wizard_creates_cash_box_and_redirects_to_units(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();

        $response = $this->actingAs($user)
            ->post(route('apartments.wizard.cash-box.store', $apartment), [
                'name' => 'Ana Kasa',
                'bank_name' => 'Ziraat Bankası',
                'iban' => 'TR000123456789',
            ]);

        $response->assertRedirect(route('apartments.wizard.units', $apartment));
        $this->assertDatabaseHas('cash_boxes', [
            'apartment_id' => $apartment->id,
            'name' => 'Ana Kasa',
        ]);
    }

    public function test_cash_box_step_redirects_to_units_when_cash_box_exists(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('apartments.wizard.cash-box', $apartment))
            ->assertRedirect(route('apartments.wizard.units', $apartment));
    }

    public function test_units_step_redirects_to_cash_box_when_no_cash_box(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();

        $this->actingAs($user)
            ->get(route('apartments.wizard.units', $apartment))
            ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
    }

    public function test_skip_units_marks_step_completed_and_redirects_to_categories(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->post(route('apartments.wizard.units.skip', $apartment));

        $response->assertRedirect(route('apartments.wizard.categories', $apartment));
        $apartment->refresh();
        $this->assertNotNull($apartment->setup_units_completed_at);
    }

    public function test_store_units_updates_units_and_marks_step_completed(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);

        $unitOne = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '01']);
        $unitTwo = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '02']);

        $response = $this->actingAs($user)
            ->post(route('apartments.wizard.units.store', $apartment), [
                'units' => [
                    $unitOne->id => ['floor' => '1', 'block' => 'A', 'square_meters' => 100, 'share_coefficient' => 0.5],
                    $unitTwo->id => ['floor' => '2', 'block' => 'B', 'square_meters' => 120, 'share_coefficient' => 0.6],
                ],
            ]);

        $response->assertRedirect(route('apartments.wizard.categories', $apartment));
        $apartment->refresh();
        $this->assertNotNull($apartment->setup_units_completed_at);

        $this->assertDatabaseHas('units', [
            'id' => $unitOne->id,
            'floor' => '1',
            'block' => 'A',
            'square_meters' => 100,
            'share_coefficient' => 0.5,
        ]);
    }

    public function test_categories_step_redirects_to_units_when_units_not_completed(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('apartments.wizard.categories', $apartment))
            ->assertRedirect(route('apartments.wizard.units', $apartment));
    }

    public function test_store_category_adds_category_and_redirects_back(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);
        $apartment->update(['setup_units_completed_at' => now()]);

        $response = $this->actingAs($user)
            ->post(route('apartments.wizard.categories.store', $apartment), [
                'name' => 'Bakım Onarım',
                'type' => 'expense',
            ]);

        $response->assertRedirect(route('apartments.wizard.categories', $apartment));
        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Bakım Onarım',
            'type' => 'expense',
        ]);
    }

    public function test_finish_completes_setup_and_redirects_to_dashboard(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();
        \App\Models\CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Ana Kasa', 'is_active' => true]);
        $apartment->update(['setup_units_completed_at' => now()]);

        $response = $this->actingAs($user)
            ->post(route('apartments.wizard.finish', $apartment));

        $response->assertRedirect(route('dashboard'));
        $apartment->refresh();
        $this->assertNotNull($apartment->setup_completed_at);
        $this->assertEquals($apartment->id, session(CurrentApartment::SESSION_KEY));
    }

    public function test_non_owner_cannot_access_wizard(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 5]);
        $owner = User::factory()->withSubscription($package)->create();
        $apartment = Apartment::factory()->forUser($owner)->create();
        $owner->apartments()->attach($apartment->id, ['role' => 'owner', 'is_active' => true]);

        $otherUser = User::factory()->create();
        $otherUser->apartments()->attach($apartment->id, ['role' => 'manager', 'is_active' => true]);

        $this->actingAs($otherUser)
            ->get(route('apartments.wizard.cash-box', $apartment))
            ->assertForbidden();
    }

    public function test_dashboard_redirects_to_wizard_when_setup_incomplete(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
    }

    public function test_switch_apartment_redirects_to_wizard_when_setup_incomplete(): void
    {
        [$user, $apartment] = $this->createOwnerWithApartment();

        $this->actingAs($user)
            ->post(route('current-apartment.update'), ['apartment_id' => $apartment->id])
            ->assertRedirect(route('apartments.wizard.cash-box', $apartment));
    }
}
