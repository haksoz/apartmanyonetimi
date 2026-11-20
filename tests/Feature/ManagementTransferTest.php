<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\ManagementOffer;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserQuotaOverride;
use App\Models\UserSubscription;
use App\Support\CurrentApartment;
use App\Support\OrderInvoicing;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManagementTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_former_manager_keeps_linked_owner_or_tenant_portal_without_management_rights(): void
    {
        foreach ([\App\Models\Account::TYPE_OWNER, \App\Models\Account::TYPE_TENANT] as $type) {
            [$old, $next, $apartment] = $this->building();
            $unit = \App\Models\Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
            $account = \App\Models\Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
                'user_id' => $old->id, 'name' => 'Eski yöneticinin dairesi', 'type' => $type, 'is_active' => true]);
            $this->accept($next, $this->offer($old, $next, $apartment))->assertSessionHasNoErrors();
            $membership = $apartment->members()->whereKey($old->id)->first()->pivot;
            $this->assertSame('member', $membership->role);
            $this->assertTrue((bool) $membership->is_active);
            $this->assertSame([$next->id], $apartment->managers()->pluck('users.id')->all());
            $this->assertSame($old->id, $account->fresh()->user_id);
            $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
                ->get(route('users.index'))->assertOk()
                ->assertViewHas('users', fn ($users) => $users->contains('id', $old->id));
            $this->actingAs($old)->get(route('subscriber.dashboard'))->assertOk()
                ->assertViewHas('residenceAccounts', fn ($accounts) => $accounts->contains('id', $account->id));
            $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
                ->get(route('residence.account', $account))->assertOk();
            $this->get(route('users.index'))->assertForbidden();
        }
    }

    public function test_matching_email_without_a_linked_portal_account_does_not_keep_old_management_access(): void
    {
        [$old, $next, $apartment] = $this->building();
        \App\Models\Account::create(['apartment_id' => $apartment->id, 'name' => 'Bağlanmamış hesap',
            'type' => \App\Models\Account::TYPE_TENANT, 'email' => $old->email, 'is_active' => true]);
        $this->accept($next, $this->offer($old, $next, $apartment))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $apartment->members()->whereKey($old->id)->first()->pivot->is_active);
        $this->assertFalse(app(CurrentApartment::class)->hasAvailableFor($old));
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->get(route('users.index'))
            ->assertOk()->assertViewHas('users', fn ($users) => ! $users->contains('id', $old->id));
        $this->get(route('users.index', ['durum' => 'eski']))->assertOk()->assertSee('Erişim kapalı')
            ->assertViewHas('users', fn ($users) => $users->contains('id', $old->id));
    }

    public function test_starting_a_transfer_requires_own_password_and_explicit_confirmation(): void
    {
        [$old, $next, $apartment] = $this->building();
        $this->actingAs($old)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->get(route('users.index'))->assertOk()->assertSee('Yöneticiliği devret')->assertSee('name="current_password"', false);
        $this->patch(route('users.role', $next), ['role' => 'owner'])->assertSessionHasErrors(['current_password', 'confirm_transfer']);
        $this->patch(route('users.role', $next), ['role' => 'owner', 'current_password' => 'wrong', 'confirm_transfer' => 1])->assertSessionHasErrors('current_password');
        $this->patch(route('users.role', $next), ['role' => 'owner', 'current_password' => 'password'])->assertSessionHasErrors('confirm_transfer');
        $this->assertDatabaseCount('management_offers', 0);
        $this->patch(route('users.role', $next), ['role' => 'owner', 'current_password' => 'password', 'confirm_transfer' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull(ManagementOffer::sole()->transfer_confirmed_at);
        $this->assertSame([$old->id], $apartment->managers()->pluck('users.id')->all());
    }

    public function test_acceptance_revokes_all_previous_managers_but_keeps_identities_paid_service_and_personal_documents(): void
    {
        [$old, $next, $apartment, $subscription] = $this->building();
        $extra = User::factory()->create();
        $apartment->members()->attach($extra->id, ['role' => 'owner', 'is_active' => true]);
        $other = Apartment::factory()->forUser($old)->create();
        $other->members()->attach($old->id, ['role' => 'owner', 'is_active' => true]);
        $profile = BillingProfile::findOrFail($this->billingSelection($old)['billing_profile_id']);
        $subscription->update(['billing_profile_id' => $profile->id]);
        $paid = $this->order($old, $apartment, $subscription, 'active');
        $paid->items()->update(['started_at' => now()->subDay(), 'expires_at' => now()->addYear()]);
        $paid->payments()->create(['amount' => 120, 'payment_date' => now(), 'payment_method' => 'havale']);
        $invoice = $paid->invoice()->create(['snapshot' => app(OrderInvoicing::class)->snapshot($paid)]);
        $invoiceSnapshot = $invoice->snapshot;
        $end = $paid->items()->first()->expires_at->toIso8601String();
        $offer = $this->offer($old, $next, $apartment);
        $this->accept($next, $offer)->assertSessionHasNoErrors();
        $this->assertSame([$next->id], $apartment->managers()->pluck('users.id')->all());
        $this->assertFalse((bool) $apartment->members()->whereKey($old->id)->first()->pivot->is_active);
        $this->assertFalse((bool) $apartment->members()->whereKey($extra->id)->first()->pivot->is_active);
        $this->assertSame([$old->id], $other->managers()->pluck('users.id')->all());
        $this->assertSame($old->id, $apartment->fresh()->user_id);
        $this->assertSame($apartment->id, $subscription->fresh()->apartment_id);
        $this->assertSame($subscription->subscription_no, $subscription->fresh()->subscription_no);
        $this->assertNull($subscription->fresh()->billing_profile_id);
        $this->assertSame($old->id, $paid->fresh()->user_id);
        $this->assertSame($invoiceSnapshot, $invoice->fresh()->snapshot);
        $this->assertSame($end, $paid->items()->first()->expires_at->toIso8601String());
        $this->assertTrue(SubscriptionItem::where('apartment_id', $apartment->id)->covering()->exists());
        $this->assertEqualsCanonicalizing([$old->id, $extra->id], $offer->fresh()->previous_manager_ids);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->actingAs($old)->get(route('subscriber.subscriptions.index'))->assertOk()->assertSee($paid->order_number);
        $this->actingAs($next)->get(route('subscriber.subscriptions.receipt', $paid))->assertForbidden();
    }

    public function test_previous_manager_cannot_purchase_after_transfer_even_using_a_stale_apartment_object(): void
    {
        [$old, $next, $apartment] = $this->building();
        $offer = $this->offer($old, $next, $apartment);
        $profile = BillingProfile::findOrFail($this->billingSelection($old)['billing_profile_id']);
        $this->accept($next, $offer)->assertSessionHasNoErrors();
        $this->assertFalse(app(CurrentApartment::class)->hasAvailableFor($old));
        $this->actingAs($old)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('users.index'))->assertRedirect(route('subscriber.dashboard'));
        try {
            app(SubscriptionCheckout::class)->openPending($old, collect([$apartment]), 'monthly', 'havale', $profile);
            $this->fail('Previous manager must not create an order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('apartment_ids', $exception->errors());
        }
        $this->assertDatabaseCount('user_subscriptions', 0);
    }

    public function test_rejection_or_failed_legal_consent_keeps_the_old_manager(): void
    {
        [$old, $next, $apartment] = $this->building();
        $offer = $this->offer($old, $next, $apartment);
        $this->actingAs($next)->post(route('management-offers.accept', $offer))->assertSessionHasErrors('accept_resident_data');
        $this->assertSame([$old->id], $apartment->managers()->pluck('users.id')->all());
        $this->post(route('management-offers.reject', $offer))->assertSessionHasNoErrors();
        $this->assertSame([$old->id], $apartment->managers()->pluck('users.id')->all());
    }

    public function test_limit_failure_does_not_remove_existing_management(): void
    {
        [$old, $next, $apartment] = $this->building();
        $another = Apartment::factory()->create();
        $another->members()->attach($next->id, ['role' => 'owner', 'is_active' => true]);
        UserQuotaOverride::create(['user_id' => $next->id, 'max_apartments' => 1]);
        $offer = $this->offer($old, $next, $apartment);
        $this->accept($next, $offer)->assertSessionHasErrors('accept');
        $this->assertSame([$old->id], $apartment->managers()->pluck('users.id')->all());
        $this->assertSame(ManagementOffer::STATUS_BLOCKED, $offer->fresh()->status);
    }

    public function test_other_pending_offers_are_invalidated_and_cannot_take_the_building_back(): void
    {
        [$old, $next, $apartment] = $this->building();
        $third = User::factory()->create();
        $apartment->members()->attach($third->id, ['role' => 'member', 'is_active' => true]);
        $offer = $this->offer($old, $next, $apartment);
        $otherOffer = $this->offer($old, $third, $apartment);
        $this->accept($next, $offer)->assertSessionHasNoErrors();
        $this->assertSame(ManagementOffer::STATUS_REJECTED, $otherOffer->fresh()->status);
        $this->accept($third, $otherOffer)->assertNotFound();
        $this->accept($next, $offer)->assertNotFound();
        $this->assertSame([$next->id], $apartment->managers()->pluck('users.id')->all());
    }

    public function test_removed_or_inactive_members_and_stale_sender_offers_cannot_be_accepted(): void
    {
        [$old, $next, $apartment] = $this->building();
        $offer = $this->offer($old, $next, $apartment);
        $apartment->members()->updateExistingPivot($next->id, ['is_active' => false]);
        $this->accept($next, $offer)->assertForbidden();
        $apartment->members()->updateExistingPivot($next->id, ['is_active' => true]);
        $apartment->members()->updateExistingPivot($old->id, ['role' => 'member']);
        $this->accept($next, $offer)->assertForbidden();
        $this->assertSame('pending', $offer->fresh()->status);
    }

    public function test_unpaid_pending_order_is_cancelled_so_it_does_not_block_the_new_manager(): void
    {
        [$old, $next, $apartment, $subscription] = $this->building();
        $pending = $this->order($old, $apartment, $subscription);
        $attempt = $pending->paymentAttempts()->create(['provider' => 'paytr', 'merchant_oid' => 'UNSUBMITTED', 'amount_minor' => 12000, 'currency' => 'TRY', 'status' => 'pending']);
        $this->accept($next, $this->offer($old, $next, $apartment))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame(UserSubscription::CANCELLED_BY_SYSTEM, $pending->fresh()->cancelled_by);
        $this->assertSame('cancelled', $attempt->fresh()->status);
        $this->assertSame($old->id, $pending->fresh()->user_id);
        $this->assertFalse($apartment->subscriptionItems()->where('status', 'pending')->exists());
    }

    public function test_submitted_payment_is_kept_for_review_instead_of_being_silently_cancelled(): void
    {
        [$old, $next, $apartment, $subscription] = $this->building();
        $pending = $this->order($old, $apartment, $subscription);
        $attempt = $pending->paymentAttempts()->create(['provider' => 'paytr', 'merchant_oid' => 'SUBMITTED', 'amount_minor' => 12000, 'currency' => 'TRY', 'status' => 'pending', 'submitted_at' => now()]);
        $this->accept($next, $this->offer($old, $next, $apartment))->assertSessionHasNoErrors();
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertTrue($attempt->fresh()->review_required);
        $this->assertSame('pending', $pending->items()->first()->status);
        $this->assertSame([$next->id], $apartment->managers()->pluck('users.id')->all());
    }

    private function building(): array
    {
        $old = User::factory()->withBankTransfer()->create(['role' => 'manager']);
        $next = User::factory()->withBankTransfer()->create(['role' => 'user']);
        $apartment = Apartment::factory()->forUser($old)->create(['unit_count' => 8]);
        $apartment->members()->attach($old->id, ['role' => 'owner', 'is_active' => true]);
        $apartment->members()->attach($next->id, ['role' => 'member', 'is_active' => true]);
        $subscription = app(SubscriptionCheckout::class)->openFree($old, $apartment);

        return [$old, $next, $apartment, $subscription];
    }

    private function offer(User $old, User $next, Apartment $apartment): ManagementOffer
    {
        return ManagementOffer::create(['apartment_id' => $apartment->id, 'user_id' => $next->id, 'offered_by' => $old->id, 'status' => 'pending', 'expires_at' => now()->addDay()]);
    }

    private function accept(User $next, ManagementOffer $offer)
    {
        return $this->actingAs($next)->post(route('management-offers.accept', $offer), ['accept_resident_data' => 1, 'accept_privacy' => 1]);
    }

    private function order(User $payer, Apartment $apartment, Subscription $subscription, string $status = 'pending'): UserSubscription
    {
        $order = UserSubscription::factory()->create([
            'user_id' => $payer->id, 'subscription_id' => $subscription->id, 'order_number' => 'SIP-26-'.strtoupper(Str::random(6)),
            'price' => 120, 'net_amount' => 100, 'tax_rate' => 20, 'tax_amount' => 20,
            'status' => $status, 'is_active' => $status === 'active', 'payment_method' => 'kredi_kartı',
        ]);
        $order->items()->create([
            'apartment_id' => $apartment->id, 'apartment_subscription_id' => $subscription->id, 'apartment_name' => $apartment->name,
            'unit_count' => 8, 'band_label' => '1–14 daire', 'band_min_units' => 1,
            'plan' => 'paid', 'status' => $status, 'currency' => 'TRY', 'amount' => 120, 'net_amount' => 100, 'tax_rate' => 20, 'tax_amount' => 20,
        ]);

        return $order;
    }
}
