<?php

namespace Tests\Feature\Admin;

use App\Models\BillingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBillingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_profiles_for_selected_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $data = ['label' => 'Müşteri faturası', 'party_type' => 'individual', 'legal_name' => 'Deneme Müşteri', 'identity_number' => '12345678901', 'address' => 'Deneme adresi'];
        $this->actingAs($admin)->post(route('admin.customers.billing-profiles.store', $customer), $data)
            ->assertRedirect(route('admin.customers.billing-profiles.index', $customer));
        $profile = $customer->billingProfiles()->sole();
        $this->get(route('admin.customers.billing-profiles.index', $customer))->assertOk()->assertSee($data['label']);
        $this->get(route('admin.customers.billing-profiles.edit', [$customer, $profile]))->assertOk()
            ->assertSee(route('admin.customers.billing-profiles.create', $customer), false);
        $data['label'] = 'Güncel profil';
        $this->put(route('admin.customers.billing-profiles.update', [$customer, $profile]), $data)->assertRedirect();
        $this->assertSame('Güncel profil', $profile->fresh()->label);
        $this->patch(route('admin.customers.billing-profiles.active', [$customer, $profile]))->assertRedirect();
        $this->assertFalse($profile->fresh()->is_active);
    }

    public function test_profile_must_belong_to_customer_in_url(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $profile = $other->billingProfiles()->create(['label' => 'Başka müşteri', 'party_type' => BillingProfile::TYPE_INDIVIDUAL, 'is_active' => true]);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.customers.billing-profiles.index', $customer))->assertOk()->assertDontSee('Başka müşteri');
        $this->get(route('admin.customers.billing-profiles.edit', [$customer, $profile]))->assertNotFound();
        $this->put(route('admin.customers.billing-profiles.update', [$customer, $profile]), [])->assertNotFound();
        $this->patch(route('admin.customers.billing-profiles.active', [$customer, $profile]))->assertNotFound();
        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_regular_customer_cannot_use_admin_profile_management(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('admin.customers.billing-profiles.index', $customer))->assertForbidden();
        $this->post(route('admin.customers.billing-profiles.store', $customer), [])->assertForbidden();
    }

    public function test_existing_party_type_cannot_be_changed(): void
    {
        $customer = User::factory()->create();
        $profile = $customer->billingProfiles()->create(['label' => 'Bireysel', 'party_type' => BillingProfile::TYPE_INDIVIDUAL]);
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.customers.billing-profiles.update', [$customer, $profile]), ['party_type' => 'corporate'])
            ->assertSessionHasErrors('party_type');
        $this->assertSame(BillingProfile::TYPE_INDIVIDUAL, $profile->fresh()->party_type);
    }
}
