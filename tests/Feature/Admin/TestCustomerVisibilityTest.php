<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\CheckoutJourney;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\AdminTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCustomerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_test_customer_and_can_reclassify_existing_customer(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post(route('admin.customers.store'), [
            'name' => 'Deneme Müşteri', 'email' => 'deneme@example.com', 'phone' => '5551234567',
            'address' => 'Deneme adresi', 'password' => 'CustomerTest123!',
            'password_confirmation' => 'CustomerTest123!', 'max_apartments' => 1, 'is_test_customer' => 1,
        ])->assertSessionHasNoErrors();
        $customer = User::where('email', 'deneme@example.com')->sole();
        $this->assertTrue($customer->is_test_customer);
        $this->assertFalse($customer->bank_transfer_allowed);
        $this->get(route('admin.customers.index'))->assertDontSee('deneme@example.com');
        $this->get(route('admin.customers.show', $customer))->assertOk()->assertSee('Test müşterisi');
        $this->patch(route('admin.customers.test-status.update', $customer), ['is_test_customer' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($customer->fresh()->is_test_customer);
        $this->get(route('admin.customers.index'))->assertSee('deneme@example.com');
    }

    public function test_setting_is_persistent_and_controls_lists_and_dashboard_without_deleting_records(): void
    {
        $admin = User::factory()->admin()->create();
        $real = User::factory()->create(['name' => 'Gercek Musteri']);
        $test = User::factory()->create(['name' => 'Deneme Musteri', 'is_test_customer' => true]);
        $realApartment = Apartment::factory()->forUser($real)->create(['name' => 'Gercek Bina']);
        $testApartment = Apartment::factory()->forUser($test)->create(['name' => 'Deneme Bina']);
        foreach ([$realApartment, $testApartment] as $apartment) {
            Subscription::create(['apartment_id' => $apartment->id, 'status' => 'active', 'started_at' => now()]);
        }
        $realOrder = UserSubscription::factory()->create(['user_id' => $real->id, 'order_number' => 'SIP-26-111111']);
        $testOrder = UserSubscription::factory()->create(['user_id' => $test->id, 'order_number' => 'SIP-26-222222']);
        foreach ([[$real, $realApartment], [$test, $testApartment]] as [$user, $apartment]) {
            CheckoutJourney::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'apartment_id' => $apartment->id, 'last_seen_at' => now()]);
        }
        $this->actingAs($admin);
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Test müşterilerini göster');
        foreach (['admin.customers.index' => ['Gercek Musteri', 'Deneme Musteri'],
            'admin.apartments.index' => ['Gercek Bina', 'Deneme Bina'],
            'admin.managers.index' => ['Gercek Bina', 'Deneme Bina'],
            'admin.orders.index' => [$realOrder->order_number, $testOrder->order_number],
            'admin.checkout-journeys.index' => ['Gercek Musteri', 'Deneme Musteri']] as $route => [$visible, $hidden]) {
            $this->get(route($route))->assertOk()->assertSee($visible)->assertDontSee($hidden);
        }
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('customerCount', 1)->assertViewHas('subscriptionCount', 1)->assertViewHas('orderCount', 1);
        $this->patch(route('admin.settings.update'), ['show_test_customers' => 1])->assertSessionHasNoErrors();
        // A different administrator sees the same saved setting.
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.customers.index'))->assertSee('Deneme Musteri');
        $this->get(route('admin.apartments.index'))->assertSee('Deneme Bina');
        $this->get(route('admin.orders.index'))->assertSee($testOrder->order_number);
        $this->get(route('admin.checkout-journeys.index'))->assertSee('Deneme Musteri');
        $this->get(route('admin.dashboard'))->assertViewHas('customerCount', 2)->assertViewHas('subscriptionCount', 2)->assertViewHas('orderCount', 2);
        $this->patch(route('admin.settings.update'), ['show_test_customers' => 0])->assertSessionHasNoErrors();
        $this->assertSame(2, Apartment::count());
        $this->assertSame(2, UserSubscription::count());
        $this->assertSame(2, CheckoutJourney::count());
    }

    public function test_test_resident_does_not_hide_real_apartment_and_management_transfer_keeps_founder_classification(): void
    {
        $real = User::factory()->create();
        $test = User::factory()->create(['is_test_customer' => true]);
        $building = Apartment::factory()->forUser($real)->create();
        $building->members()->attach($test, ['role' => 'resident', 'is_active' => true]);
        $testBuilding = Apartment::factory()->forUser($test)->create();
        $testBuilding->members()->attach($real, ['role' => 'owner', 'is_active' => true]);
        $this->assertSame([$building->id], Apartment::query()->tap([AdminTestData::class, 'apply'])->pluck('id')->all());
        $test->is_test_customer = false;
        $test->save();
        $this->assertSame(2, Apartment::query()->tap([AdminTestData::class, 'apply'])->count());
    }

    public function test_non_admin_cannot_change_test_flags_or_visibility_and_flag_is_not_mass_assignable(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer);
        $this->patch(route('admin.settings.update'), ['show_test_customers' => 1])->assertForbidden();
        $this->patch(route('admin.customers.test-status.update', $customer), ['is_test_customer' => 1])->assertForbidden();
        $this->assertFalse($customer->fresh()->is_test_customer);
        $this->assertFalse((new User)->isFillable('is_test_customer'));
        $this->assertNull(SystemSetting::query()->value('show_test_customers'));
    }

    public function test_admin_flag_validation_does_not_mutate_customer(): void
    {
        $customer = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->patch(route('admin.customers.test-status.update', $customer), ['is_test_customer' => 'invalid'])->assertSessionHasErrors('is_test_customer');
        $this->patch(route('admin.settings.update'), ['show_test_customers' => 'invalid'])->assertSessionHasErrors('show_test_customers');
        $this->assertFalse($customer->fresh()->is_test_customer);
    }

    public function test_payment_attempts_and_archived_history_follow_customer_flag_while_payment_data_remains_accessible(): void
    {
        $customer = User::factory()->create(['is_test_customer' => true]);
        $building = Apartment::factory()->forUser($customer)->create(['is_active' => false]);
        $order = UserSubscription::factory()->create(['user_id' => $customer->id]);
        $attempt = \App\Models\SubscriptionPaymentAttempt::create([
            'subscription_id' => $order->id, 'provider' => 'paytr', 'merchant_oid' => 'TESTVISIBILITY123',
            'amount_minor' => 18000, 'currency' => 'TL', 'is_test' => false, 'status' => 'pending',
        ]);
        $item = \App\Models\SubscriptionItem::create([
            'subscription_id' => $order->id, 'apartment_id' => $building->id, 'apartment_name' => $building->name,
            'unit_count' => 10, 'band_label' => '1–14 daire', 'band_min_units' => 1, 'amount' => 180,
            'currency' => 'TRY', 'plan' => 'paid', 'status' => 'active', 'started_at' => now(),
        ]);
        $operation = \App\Models\ApartmentDataOperation::create([
            'user_id' => $customer->id, 'source_apartment_id' => $building->id, 'apartment_name' => $building->name,
            'actor_name' => $customer->name, 'action' => 'operational_wipe', 'result' => 'completed', 'archive_status' => 'not_required',
        ]);
        $quote = \App\Models\QuoteRequest::record($customer, 'Test Teklif', 101);
        $retained = Subscription::create(['retained_user_id' => $customer->id, 'apartment_name' => 'Silinen test binası', 'status' => 'ended', 'started_at' => now()]);
        foreach ([$building, $attempt, $item, $operation, $quote, $retained] as $model) {
            $this->assertFalse($model->newQuery()->tap([AdminTestData::class, 'apply'])->whereKey($model->id)->exists());
            $this->assertTrue($model->newQuery()->whereKey($model->id)->exists());
        }
        // Even a live-mode financial attempt is only hidden from overviews, never changed or deleted.
        $this->assertFalse($attempt->fresh()->is_test);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame($order->id, $attempt->fresh()->subscription->id);
        $this->actingAs(User::factory()->admin()->create());
        $this->patch(route('admin.settings.update'), ['show_test_customers' => 1])->assertSessionHasNoErrors();
        foreach ([$building, $attempt, $item, $operation, $quote, $retained] as $model) {
            $this->assertTrue($model->newQuery()->tap([AdminTestData::class, 'apply'])->whereKey($model->id)->exists());
        }
    }
}
