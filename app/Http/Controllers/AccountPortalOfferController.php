<?php

namespace App\Http\Controllers;

use App\Models\AccountPortalOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountPortalOfferController extends Controller
{
    public function accept(AccountPortalOffer $offer)
    {
        $this->authorizeOffer($offer);

        $linked = DB::transaction(function () use ($offer) {
            $account = $offer->account()->lockForUpdate()->first();

            if (! $account || $account->apartment_id !== $offer->apartment_id) {
                $offer->update([
                    'status' => AccountPortalOffer::STATUS_REJECTED,
                    'rejected_at' => now(),
                ]);

                return false;
            }

            if ($account->user_id && $account->user_id !== $offer->user_id) {
                $offer->update([
                    'status' => AccountPortalOffer::STATUS_REJECTED,
                    'rejected_at' => now(),
                ]);

                return false;
            }

            $account->update(['user_id' => $offer->user_id]);

            $membership = $account->apartment->members()->whereKey($offer->user_id)->exists();

            if (! $membership) {
                $account->apartment->members()->attach($offer->user_id, [
                    'role' => 'member',
                    'is_active' => true,
                ]);
            }

            $offer->update([
                'status' => AccountPortalOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            return true;
        });

        return back()->with('status', $linked
            ? 'Daveti kabul ettiniz.'
            : 'Bu davet artık geçerli değil.');
    }

    public function reject(AccountPortalOffer $offer)
    {
        $this->authorizeOffer($offer);

        $offer->update([
            'status' => AccountPortalOffer::STATUS_REJECTED,
            'rejected_at' => now(),
        ]);

        return back()->with('status', 'Daveti reddettiniz.');
    }

    private function authorizeOffer(AccountPortalOffer $offer): void
    {
        abort_unless(
            $offer->user_id === auth()->id() && $offer->status === AccountPortalOffer::STATUS_PENDING,
            403,
        );
    }
}
