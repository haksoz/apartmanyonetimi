<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_manager_and_return(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->withSubscription()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.impersonate.start', $manager));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($manager);
        $this->assertEquals($admin->id, session('impersonate_admin_id'));

        $leave = $this->post(route('admin.impersonate.leave'));

        $leave->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonate_admin_id'));
    }

    public function test_manager_cannot_impersonate(): void
    {
        $manager = User::factory()->withSubscription()->create();
        $target = User::factory()->withSubscription()->create();

        $this->actingAs($manager)
            ->post(route('admin.impersonate.start', $target))
            ->assertStatus(403);
    }

    public function test_admin_cannot_impersonate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', $otherAdmin))
            ->assertStatus(403);
    }
}
