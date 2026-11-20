<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class ManagementTransfer
{
    /** Apartment and offer must be locked by the enclosing transaction. */
    public function complete(Apartment $apartment, User $user, ManagementOffer $offer): void
    {
        $firstCustomerMembership = ! $user->isAdminPanelUser()
            && ! $user->isSubscriber()
            && ! $user->ownedApartments()->withTrashed()->exists()
            && ! $user->managementPeriods()->exists()
            && ! $user->apartments()->wherePivot('role', 'owner')->exists()
            && ! $user->subscriptions()->withTrashed()->commercial()->exists();
        if ($firstCustomerMembership) {
            $user->quotaOverride()->firstOrCreate([], ['max_apartments' => 1]);
            $user->update(['role' => User::ROLE_MANAGER]);
            $user->unsetRelation('quotaOverride');
        }
        app(ApartmentManagementHistory::class)->transfer($apartment, $user, $offer);
        $previous = $apartment->members()->wherePivot('role', 'owner')->where('users.id', '!=', $user->id)->pluck('users.id')->all();
        DB::table('apartment_user')->where('apartment_id', $apartment->id)
            ->where('user_id', '!=', $user->id)->where('role', 'owner')
            ->update(['role' => 'member', 'is_active' => false, 'updated_at' => now()]);
        $apartment->members()->updateExistingPivot($user->id, ['role' => 'owner', 'is_active' => true]);
        Subscription::where('apartment_id', $apartment->id)->update(['billing_profile_id' => null]);
        $this->closeUnpaidOrders($apartment, $previous);
        $offer->update(['status' => ManagementOffer::STATUS_ACCEPTED, 'accepted_at' => $offer->accepted_at ?? now(), 'previous_manager_ids' => $previous]);
        ManagementOffer::where('apartment_id', $apartment->id)->whereKeyNot($offer->id)
            ->whereIn('status', [ManagementOffer::STATUS_PENDING, ManagementOffer::STATUS_BLOCKED])
            ->update(['status' => ManagementOffer::STATUS_REJECTED, 'rejected_at' => now(), 'expires_at' => null]);
    }

    private function closeUnpaidOrders(Apartment $apartment, array $previous): void
    {
        $orders = UserSubscription::whereIn('user_id', $previous)->where('status', UserSubscription::STATUS_PENDING)
            ->whereHas('items', fn ($query) => $query->where('apartment_id', $apartment->id))
            ->whereDoesntHave('payments', fn ($query) => $query->withTrashed())
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($orders as $order) {
            $attempts = $order->paymentAttempts()->lockForUpdate()->get();
            $uncertain = $attempts->contains(fn ($attempt) => $attempt->review_required
                || in_array($attempt->status, ['unknown', 'succeeded'], true)
                || ($attempt->provider === 'paytr' && $attempt->status === 'pending' && $attempt->submitted_at !== null));
            $note = trim(($order->notes ?? '')."\nYöneticilik devri: ".now()->toIso8601String());
            // Submitted payments and bank-transfer proofs require reconciliation, never silent cancellation.
            $multiApartment = $order->items()->where(fn ($query) => $query->where('apartment_id', '!=', $apartment->id)->orWhereNull('apartment_id'))->exists();
            if ($uncertain || $order->hasPaymentProof() || $multiApartment) {
                $order->update(['notes' => $note]);
                $order->paymentAttempts()->whereIn('status', ['pending', 'unknown', 'succeeded'])->update(['review_required' => true]);

                continue;
            }
            $order->paymentAttempts()->where('status', 'pending')->whereNull('submitted_at')->update(['status' => 'cancelled']);
            app(CancelSubscriptionOrder::class)->cancel($order, UserSubscription::CANCELLED_BY_SYSTEM, $note);
        }
    }
}
