<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_logout_url_displays_post_form_and_keeps_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['logout_test' => 'preserved'])
            ->get(route('logout'))->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSessionHas('logout_test', 'preserved');
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'))
            ->assertSessionMissing('logout_test');
        $this->assertGuest();
    }

    public function test_guest_opening_logout_url_is_redirected_to_login(): void
    {
        $this->get(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
