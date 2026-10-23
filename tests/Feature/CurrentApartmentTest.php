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
            'setup_completed_at' => now(),
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
            'setup_completed_at' => now(),
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
            ->assertRedirect(route('apartments.wizard.cash-box', $secondApartment));
    }

    public function test_apartment_pages_redirect_to_subscriber_dashboard_without_selection(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Seçilecek Apartman',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);
        foreach (['dashboard', 'dues.index', 'cash.index', 'residence.home'] as $route) {
            $this->get(route($route))->assertRedirect(route('subscriber.dashboard'));
        }

        $this->get(route('subscriber.dashboard'))->assertOk();
    }

    public function test_login_redirects_manager_with_apartments_to_subscriber_dashboard(): void
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
        ])->assertRedirect(route('subscriber.dashboard'));
    }

    public function test_old_selection_url_redirects_to_subscriber_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('current-apartment.select'))
            ->assertRedirect(route('subscriber.dashboard'));
    }

    public function test_logout_and_login_returns_to_subscriber_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $apartment = Apartment::factory()->create(['user_id' => $user->id, 'setup_completed_at' => now()]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session(CurrentApartment::SESSION_KEY));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('subscriber.dashboard'));
        $this->get(route('subscriber.dashboard'))->assertOk()->assertSee($apartment->name);
        $this->get(route('dashboard'))->assertRedirect(route('subscriber.dashboard'));
    }
}
