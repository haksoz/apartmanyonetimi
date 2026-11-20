<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\CheckoutJourney;
use App\Support\CheckoutJourneyTracking;
use App\Support\PriceQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CheckoutJourneyController extends Controller
{
    public function store(Request $request, string $uuid, CheckoutJourneyTracking $tracking)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['viewed', 'changed', 'closed', 'accepted', 'validation_failed', 'activity', 'renewed', 'timed_out', 'timeout_closed', 'timeout_prompt', 'timeout_dismissed'])],
            'stage' => ['required', Rule::in(['plan', 'billing', 'payment', 'distance', 'pre'])],
            'period' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['nullable', Rule::in(['kredi_kartı', 'havale'])],
            'billing_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'billing_profile_id' => ['nullable', 'integer'],
            'billing_party_type' => ['nullable', Rule::in(['individual', 'corporate', 'other'])],
            'field' => ['nullable', Rule::in(['period', 'payment_method', 'billing_mode', 'billing_profile_id', 'billing_party_type'])],
        ]);

        // Only values visible at the reported stage belong in its audit entry.
        if ($data['stage'] === 'plan') {
            unset($data['billing_mode'], $data['billing_profile_id'], $data['billing_party_type']);
        }
        if (in_array($data['stage'], ['plan', 'billing'], true)) {
            unset($data['payment_method']);
        }

        return DB::transaction(function () use ($request, $uuid, $data, $tracking) {
            $journey = CheckoutJourney::where('uuid', $uuid)->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($journey->order_id || $journey->closed_at) {
                if ($data['type'] === 'renewed') return response()->json(['message' => 'Bu süreç kapanmış. Yeni bir işlem başlatın.'], 409);
                return response()->noContent();
            }
            abort_unless($journey->apartment && $journey->apartment->is_active && ! $journey->apartment->trashed()
                && $journey->apartment->members()->where('users.id', $request->user()->id)
                    ->where('apartment_user.role', 'owner')->where('apartment_user.is_active', true)->exists(), 403);
            if (in_array($data['type'], ['timed_out', 'timeout_prompt'], true)) {
                $renewalId = $journey->events()->where('type', 'renewed')->latest('id')->value('id') ?? 0;
                if ($journey->isFormTimedOut() && ! $journey->events()->where('type', $data['type'])->where('id', '>', $renewalId)->where('created_at', '>=', $journey->last_seen_at)->exists()) {
                    $tracking->event($journey, $data['type'], 'browser', ['stage' => $journey->stage]);
                }
                return response()->noContent();
            }
            if ($data['type'] === 'timeout_closed' && $journey->last_seen_at->gt(now()->subSeconds(CheckoutJourney::FORM_TIMEOUT_SECONDS + CheckoutJourney::RENEWAL_RESPONSE_SECONDS))) {
                return response()->json(['message' => 'Yanıt süresi henüz dolmadı.'], 409);
            }
            if (! in_array($data['type'], ['renewed', 'closed', 'timeout_closed', 'timeout_dismissed'], true) && $journey->isFormTimedOut()) {
                return response()->json(['message' => 'Devam etmek için süreyi uzatın.'], 409);
            }
            if (in_array($data['type'], ['activity', 'renewed'], true)) {
                $journey->update(['last_seen_at' => now()]);
                if ($data['type'] === 'renewed') $tracking->event($journey, 'renewed', 'browser', ['stage' => $journey->stage]);
                return response()->noContent();
            }
            $profileId = $data['billing_profile_id'] ?? null;
            $profile = null;
            if ($profileId) {
                $profile = $request->user()->billingProfiles()->whereKey($profileId)->where('is_active', true)->first();
                abort_unless($profile, 422);
            }
            $details = $data;
            unset($details['type']);
            if (! empty($data['payment_method'])) {
                $methods = app(\App\Support\CheckoutPaymentMethods::class)->forUser($request->user());
                $details['payment_method_automatic'] = count($methods) === 1 && $methods[0] === $data['payment_method'];
            }
            if ($profile) {
                $details['billing_profile_label'] = $profile->label;
                $details['billing_party_type'] = $profile->party_type;
            }
            $attributes = ['stage' => $data['stage'], 'last_seen_at' => now()];
            foreach (['period', 'payment_method', 'billing_profile_id'] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }
            if (! empty($data['period'])) {
                $quote = app(PriceQuote::class)->forUnits((int) $journey->apartment->unit_count, $data['period'], $journey->apartment);
                $attributes['quoted_amount'] = $quote['amount'] ?? null;
                $details['amount'] = $attributes['quoted_amount'];
            }
            if (in_array($data['type'], ['closed', 'timeout_closed', 'timeout_dismissed'], true)) {
                $attributes['closed_at'] = now();
            }
            $journey->update($attributes);
            $tracking->event($journey, $data['type'], 'browser', $details);

            return response()->noContent();
        });
    }
}
