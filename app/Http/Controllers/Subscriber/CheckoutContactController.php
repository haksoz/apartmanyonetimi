<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\CheckoutJourney;
use App\Models\User;
use App\Support\CheckoutJourneyTracking;
use App\Support\Paytr\PayerContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutContactController extends Controller
{
    public function update(Request $request, CheckoutJourneyTracking $tracking)
    {
        $request->validate(['checkout_journey' => ['required', 'uuid']]);

        return DB::transaction(function () use ($request, $tracking) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $journey = CheckoutJourney::where('uuid', $request->input('checkout_journey'))
                ->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            abort_unless($journey->apartment && $journey->apartment->is_active && ! $journey->apartment->trashed()
                && $journey->apartment->members()->where('users.id', $user->id)
                    ->where('apartment_user.role', 'owner')->where('apartment_user.is_active', true)->exists(), 403);
            if ($journey->order_id || $journey->closed_at) {
                return response()->json(['message' => 'Bu süreç kapanmış. Yeni bir işlem başlatın.'], 409);
            }
            if ($journey->isFormTimedOut()) {
                return response()->json(['message' => 'Önce işlem süresini uzatın.', 'renewal_required' => true], 409);
            }

            $fields = PayerContact::fieldsToComplete($user);
            $rules = [];
            foreach ($fields as $field) $rules[$field] = PayerContact::rules()['payer_'.$field];
            $validated = $request->validate($rules, [
                'phone.required' => 'Telefon numaranızı girin.',
                'phone.regex' => 'Telefonu başında 0 olmadan 10 hane girin. Örnek: 5551234567.',
                'address.required' => 'İletişim adresinizi girin.',
                'address.max' => 'İletişim adresi en fazla 400 karakter olabilir.',
            ]);
            // Valid existing profile fields and any other submitted attributes cannot be overwritten here.
            if ($validated !== []) $user->update($validated);
            $journey->update(['last_seen_at' => now()]);
            if ($fields !== []) $tracking->event($journey, 'contact_completed', 'server', ['fields' => $fields]);

            return response()->json(['contact' => PayerContact::fromUser($user)]);
        });
    }
}
