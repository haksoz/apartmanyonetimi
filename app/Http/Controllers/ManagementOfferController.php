<?php

namespace App\Http\Controllers;

use App\Models\ManagementOffer;
use App\Support\CurrentApartment;
use App\Support\LegalConsent;
use App\Support\UserApartmentQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagementOfferController extends Controller
{
    public function show(ManagementOffer $offer, LegalConsent $consent)
    {
        $this->authorizeOffer($offer);

        return view('management-offers.show', [
            'offer' => $offer->load('apartment'),
            'needsResidentData' => $consent->needsResidentData(auth()->user(), $offer->apartment),
        ]);
    }

    public function accept(Request $request, ManagementOffer $offer, LegalConsent $consent, UserApartmentQuota $quota)
    {
        $this->authorizeOffer($offer);

        $user = $request->user();
        $apartment = $offer->apartment;

        if ($consent->needsResidentData($user, $apartment)) {
            $request->validate([
                'accept_resident_data' => ['accepted'],
                'accept_privacy' => ['accepted'],
            ], [
                'accept_resident_data.accepted' => 'Devam etmek için daire sakini verisi bildirimini kabul edin.',
                'accept_privacy.accepted' => 'Devam etmek için gizlilik ve KVKK aydınlatmasını kabul edin.',
            ]);
        }

        $blocked = false;

        DB::transaction(function () use ($offer, $user, $apartment, $quota, $consent, $request, &$blocked) {
            $locked = ManagementOffer::query()->whereKey($offer->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isOpen()) {
                abort(404);
            }

            if (! $quota->hasRoomForManagement($user)) {
                $locked->update([
                    'status' => ManagementOffer::STATUS_BLOCKED,
                    'expires_at' => null,
                ]);
                $blocked = true;

                return;
            }

            if ($consent->needsResidentData($user, $apartment)) {
                $consent->recordResidentData($user, $apartment, $request);
            }

            $apartment->members()->updateExistingPivot($user->id, [
                'role' => 'owner',
                'is_active' => true,
            ]);

            $locked->update([
                'status' => ManagementOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);
        });

        if ($blocked) {
            return back()->withErrors(['accept' => UserApartmentQuota::CONTACT_MESSAGE]);
        }

        return redirect()
            ->route('subscriber.dashboard')
            ->with('status', 'Yöneticilik kabul edildi.');
    }

    public function reject(ManagementOffer $offer)
    {
        $this->authorizeOffer($offer);

        $offer->update([
            'status' => ManagementOffer::STATUS_REJECTED,
            'rejected_at' => now(),
        ]);

        app(CurrentApartment::class)->setFor($offer->user, $offer->apartment_id);

        return redirect()
            ->route('residence.home')
            ->with('status', 'Yöneticilik teklifi reddedildi. Daire üyeliğiniz duruyor.');
    }

    private function authorizeOffer(ManagementOffer $offer): void
    {
        abort_unless($offer->isOpen(), 404);
        abort_unless((int) auth()->id() === (int) $offer->user_id, 403);
    }
}
