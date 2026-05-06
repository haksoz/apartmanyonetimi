<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentApartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_between_owned_apartments(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)->post(route('current-apartment.update'), [
            'apartment_id' => $secondApartment->id,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($secondApartment->id, session(CurrentApartment::SESSION_KEY));
    }

    public function test_dashboard_uses_selected_apartment_context(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        Account::create([
            'apartment_id' => $firstApartment->id,
            'type' => 'resident',
            'name' => 'Birinci Daire',
        ]);
        Account::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'resident',
            'name' => 'İkinci Daire 1',
        ]);
        Account::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'resident',
            'name' => 'İkinci Daire 2',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee('İkinci Apartman')
            ->assertSee('2');
    }

    public function test_dashboard_redirects_to_apartment_selection_when_user_has_apartments_but_no_selection(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Seçilecek Apartman',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('current-apartment.select'));
    }

    public function test_login_redirects_manager_with_apartments_to_selection_screen(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Giriş Sonrası Seçilecek Apartman',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('current-apartment.select'));
    }
}
