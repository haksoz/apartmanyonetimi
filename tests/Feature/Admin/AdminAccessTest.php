<?php

namespace Tests\Feature\Admin;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertStatus(200);
    }

    public function test_admin_can_access_managers_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.managers.index'))
            ->assertStatus(200);
    }

    public function test_admin_can_access_packages_list(): void
    {
        $admin = User::factory()->admin()->create();
        Package::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.packages.index'))
            ->assertStatus(200);
    }

    public function test_manager_cannot_access_admin_routes(): void
    {
        $manager = User::factory()->withSubscription()->create();

        $this->actingAs($manager)
            ->get(route('admin.dashboard'))
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }
}
