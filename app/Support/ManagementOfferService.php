<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\ManagementOffer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagementOfferService
{
    public const OFFER_BLOCKED_MESSAGE = 'Bu kişinin yönetim hakkı dolu. Yönetici olabilmesi için kendisinin AidatCep ile iletişime geçmesi gerekir.';

    public function __construct(private UserApartmentQuota $quota) {}

    public function offer(Apartment $apartment, User $user, User $offeredBy, Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirm_transfer' => ['accepted'],
        ], [
            'current_password.required' => 'Yöneticilik devri için kendi parolanızı girin.',
            'confirm_transfer.accepted' => 'Kabul sonrası apartman erişiminizin kapanacağını onaylayın.',
        ]);

        return DB::transaction(function () use ($apartment, $user, $offeredBy) {
            $apartment = Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            abort_unless($apartment->is_active, 403);
            abort_unless($offeredBy->isAdmin() || $apartment->managers()->whereKey($offeredBy->id)->exists(), 403);
            $membership = $apartment->members()->whereKey($user->id)->first();
            if (! $membership || ! $membership->pivot->is_active) {
                return back()->withErrors(['role' => 'Erişimi kapalı bir kişiye yöneticilik teklif edilemez.']);
            }
            if ($membership->pivot->role === 'owner') {
                return back()->with('status', 'Bu kişi zaten yönetici.');
            }
            $pending = ManagementOffer::where('apartment_id', $apartment->id)->where('user_id', $user->id)->openPending()->first();
            if ($pending) {
                return back()->with('status', 'Yöneticilik daveti kullanıcının panelinde gösteriliyor. Kabulü bekleniyor.');
            }
            if (! $this->quota->hasRoomForManagement($user)) {
                ManagementOffer::updateOrCreate([
                    'apartment_id' => $apartment->id, 'user_id' => $user->id, 'status' => ManagementOffer::STATUS_BLOCKED,
                ], ['offered_by' => $offeredBy->id, 'expires_at' => null]);

                return back()->withErrors(['role' => self::OFFER_BLOCKED_MESSAGE]);
            }
            ManagementOffer::create([
                'apartment_id' => $apartment->id, 'user_id' => $user->id, 'offered_by' => $offeredBy->id,
                'status' => ManagementOffer::STATUS_PENDING, 'expires_at' => now()->addDays(14),
                'transfer_confirmed_at' => now(),
            ]);

            return back()->with('status', 'Yöneticilik daveti kullanıcının panelinde gösteriliyor. Kabulü bekleniyor.');
        });
    }
}
