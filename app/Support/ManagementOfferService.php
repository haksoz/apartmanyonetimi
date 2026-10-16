<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ManagementOfferService
{
    public const OFFER_BLOCKED_MESSAGE = 'Bu kişinin yönetim hakkı dolu. Yönetici olabilmesi için kendisinin AidatCep ile iletişime geçmesi gerekir.';

    public function __construct(private UserApartmentQuota $quota) {}

    public function offer(Apartment $apartment, User $user, User $offeredBy): RedirectResponse
    {
        $membership = $apartment->members()->whereKey($user->id)->first();

        if (! $membership || ! $membership->pivot->is_active) {
            return back()->withErrors(['role' => 'Erişimi kapalı bir kişiye yöneticilik teklif edilemez.']);
        }

        if ($membership->pivot->role === 'owner') {
            return back()->with('status', 'Bu kişi zaten yönetici.');
        }

        $pending = ManagementOffer::query()
            ->where('apartment_id', $apartment->id)
            ->where('user_id', $user->id)
            ->openPending()
            ->first();

        if ($pending) {
            return back()
                ->with('status', 'Yöneticilik kabulü bekleniyor.')
                ->with('management_offer_url', route('management-offers.show', $pending));
        }

        if (! $this->quota->hasRoomForManagement($user)) {
            ManagementOffer::query()->updateOrCreate(
                [
                    'apartment_id' => $apartment->id,
                    'user_id' => $user->id,
                    'status' => ManagementOffer::STATUS_BLOCKED,
                ],
                [
                    'offered_by' => $offeredBy->id,
                    'expires_at' => null,
                ],
            );

            return back()->withErrors(['role' => self::OFFER_BLOCKED_MESSAGE]);
        }

        $offer = ManagementOffer::query()->create([
            'apartment_id' => $apartment->id,
            'user_id' => $user->id,
            'offered_by' => $offeredBy->id,
            'status' => ManagementOffer::STATUS_PENDING,
            'expires_at' => now()->addDays(14),
        ]);

        return back()
            ->with('status', 'Yöneticilik kabulü bekleniyor. Bağlantıyı kişiye iletin.')
            ->with('management_offer_url', route('management-offers.show', $offer));
    }
}
