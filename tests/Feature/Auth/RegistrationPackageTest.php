<?php

namespace Tests\Feature\Auth;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_shows_packages(): void
    {
        $package = Package::factory()->create(['is_active' => true]);

        $this->get(route('register'))
            ->assertStatus(200)
            ->assertSee($package->name);
    }

    public function test_registration_creates_subscription_and_redirects_to_onboarding(): void
    {
        $package = Package::factory()->create(['apartment_limit' => 3]);

        $response = $this->post(route('register'), [
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'package_id' => $package->id,
            'period' => 'yearly',
        ]);

        $response->assertRedirect(route('onboarding.show'));

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'package_id' => $package->id,
            'period' => 'yearly',
            'price' => $package->yearly_price,
            'is_active' => true,
        ]);
    }
}
