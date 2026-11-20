<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\UserApartmentQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerCreationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Test Müşteri', 'email' => 'new-customer@example.com',
            'phone' => '5551234567', 'password' => 'CustomerTest123!',
            'password_confirmation' => 'CustomerTest123!', 'max_apartments' => 1,
        ], $overrides);
    }

    public function test_admin_can_create_active_customer_with_closed_public_registration_without_changing_admin_session(): void
    {
        config(['registration.enabled' => false]);
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->get(route('admin.customers.index'))->assertOk()->assertSee('Müşteri ekle');
        $this->get(route('admin.customers.create'))->assertOk()->assertSee('Müşteriyi oluştur');
        $this->post(route('admin.customers.store'), $this->payload([
            'email' => ' New-Customer@Example.com ', 'role' => User::ROLE_SUPER_ADMIN,
            'bank_transfer_allowed' => true, 'deactivated_at' => now(),
        ]))->assertSessionHasNoErrors();
        $customer = User::where('email', 'new-customer@example.com')->sole();
        $this->assertSame(User::ROLE_MANAGER, $customer->role);
        $this->assertNotNull($customer->activated_at);
        $this->assertNull($customer->deactivated_at);
        $this->assertNull($customer->email_verified_at);
        $this->assertFalse($customer->bank_transfer_allowed);
        $this->assertTrue(Hash::check('CustomerTest123!', $customer->password));
        $this->assertSame(1, app(UserApartmentQuota::class)->definedLimit($customer));
        $this->assertTrue(app(UserApartmentQuota::class)->canCreate($customer));
        $this->assertSame(0, $customer->ownedApartments()->count());
        $this->assertSame(0, $customer->subscriptions()->count());
        $this->assertDatabaseMissing('legal_acceptances', ['user_id' => $customer->id]);
        $this->assertAuthenticatedAs($admin);
        Notification::assertNothingSent();
        $this->get(route('admin.customers.show', $customer))->assertOk()->assertSee('Test Müşteri');
        $this->get(route('admin.customers.index', ['search' => $customer->email]))->assertSee($customer->email);
    }

    public function test_created_customer_can_log_in_and_has_configured_quota_with_optional_phone(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.customers.store'), $this->payload(['phone' => '', 'max_apartments' => 3]))->assertSessionHasNoErrors();
        $customer = User::where('email', 'new-customer@example.com')->sole();
        $this->assertNull($customer->phone);
        $this->assertSame(3, app(UserApartmentQuota::class)->definedLimit($customer));
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $customer->email, 'password' => 'CustomerTest123!'])
            ->assertRedirect(route('subscriber.dashboard'));
        $this->assertAuthenticatedAs($customer);
        $this->get(route('subscriber.dashboard'))->assertOk();
    }

    public function test_invalid_or_duplicate_input_does_not_create_customer_or_modify_existing_invitation(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com', 'activated_at' => null]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([
            [['email' => 'EXISTING@example.com'], 'email'],
            [['email' => 'invalid'], 'email'],
            [['phone' => '05551234567'], 'phone'],
            [['phone' => '+905551234567'], 'phone'],
            [['phone' => '555 1234567'], 'phone'],
            [['password_confirmation' => 'different'], 'password'],
            [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            [['max_apartments' => 0], 'max_apartments'],
            [['max_apartments' => 1001], 'max_apartments'],
            [['name' => ''], 'name'],
        ] as [$overrides, $field]) {
            $this->post(route('admin.customers.store'), $this->payload($overrides))->assertSessionHasErrors($field);
            $this->assertDatabaseCount('users', 2);
            $this->assertDatabaseCount('user_quota_overrides', 0);
        }
        $this->assertNull($existing->fresh()->activated_at);
    }

    public function test_guest_and_customer_cannot_create_customers(): void
    {
        $this->get(route('admin.customers.create'))->assertRedirect(route('login'));
        $this->post(route('admin.customers.store'), $this->payload())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.customers.create'))->assertForbidden();
        $this->post(route('admin.customers.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new-customer@example.com']);
    }
}
