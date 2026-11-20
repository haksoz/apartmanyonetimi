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
            'type' => ['required', Rule::in(['viewed', 'changed', 'closed', 'accepted', 'validation_failed'])],
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
                return response()->noContent();
            }
            abort_unless($journey->apartment && $journey->apartment->is_active && ! $journey->apartment->trashed()
                && $journey->apartment->members()->where('users.id', $request->user()->id)
                    ->where('apartment_user.role', 'owner')->where('apartment_user.is_active', true)->exists(), 403);
            $profileId = $data['billing_profile_id'] ?? null;
            $profile = null;
            if ($profileId) {
                $profile = $request->user()->billingProfiles()->whereKey($profileId)->where('is_active', true)->first();
                abort_unless($profile, 422);
            }
            $details = $data;
            unset($details['type']);
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
            if ($data['type'] === 'closed') {
                $attributes['closed_at'] = now();
            }
            $journey->update($attributes);
            $tracking->event($journey, $data['type'], 'browser', $details);

            return response()->noContent();
        });
    }
}
