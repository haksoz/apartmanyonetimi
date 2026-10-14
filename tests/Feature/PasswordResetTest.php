<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_the_reset_request(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee('Şifremi unuttum');
    }

    public function test_an_activated_user_receives_a_link_and_can_set_a_new_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'sifre@example.com',
            'role' => 'manager',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
        ])->assertRedirect(route('subscriber.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('yeni-parola', $user->fresh()->password));
    }

    public function test_an_invited_user_is_sent_back_to_the_invite_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'davetli@example.com',
            'role' => 'user',
            'activated_at' => null,
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_an_unknown_email_gets_the_same_confirmation(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'yok@example.com'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
    }

    public function test_a_used_token_cannot_reset_the_password_again(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'tekrar@example.com']);

        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'yeni-parola',
            'password_confirmation' => 'yeni-parola',
        ];

        $this->post(route('password.update'), $payload)->assertRedirect();
        $this->post(route('logout'));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'baska-parola',
            'password_confirmation' => 'baska-parola',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('yeni-parola', $user->fresh()->password));
    }
}
