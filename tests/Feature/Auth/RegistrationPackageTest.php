<?php

namespace Tests\Feature\Auth;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_does_not_list_packages(): void
    {
        $package = Package::factory()->create(['is_active' => true, 'name' => 'Deneme Paketi']);

        $this->get(route('register'))
            ->assertOk()
            ->assertDontSee($package->name);
    }

    public function test_registration_does_not_create_a_subscription(): void
    {
        $response = $this->withSession([
            'register_challenge' => [
                'a' => 2,
                'b' => 3,
                'sum' => 5,
                'opened_at' => now()->subSeconds(10)->timestamp,
            ],
        ])->post(route('register'), [
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'human_answer' => 5,
        ]);

        $response->assertRedirect(route('subscriber.dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
            'role' => 'manager',
        ]);

        $this->assertDatabaseCount('user_subscriptions', 0);
    }
}
