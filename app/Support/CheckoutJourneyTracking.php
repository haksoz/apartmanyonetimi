<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\CheckoutJourney;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Str;

class CheckoutJourneyTracking
{
    public function begin(User $user, Apartment $apartment, mixed $uuid = null): CheckoutJourney
    {
        if (is_string($uuid) && Str::isUuid($uuid)) {
            $existing = CheckoutJourney::where('uuid', $uuid)->where('user_id', $user->id)->where('apartment_id', $apartment->id)->first();
            if ($existing && ! $existing->order_id && ! $existing->closed_at) {
                return $existing;
            }
        }
        $journey = CheckoutJourney::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'apartment_id' => $apartment->id, 'stage' => 'plan', 'last_seen_at' => now()]);
        $this->event($journey, 'started', 'server');

        return $journey;
    }

    public function event(CheckoutJourney $journey, string $type, string $source, array $details = []): void
    {
        $journey->events()->create(['type' => $type, 'source' => $source, 'details' => $details]);
    }

    public function attach(UserSubscription $order, ?string $uuid): void
    {
        $journey = $uuid ? CheckoutJourney::where('uuid', $uuid)->where('user_id', $order->user_id)->lockForUpdate()->first() : null;
        $apartmentId = $order->items()->value('apartment_id');
        if ($journey && ($journey->apartment_id !== $apartmentId || ($journey->order_id && $journey->order_id !== $order->id))) {
            $journey = null;
        }
        if (! $journey) {
            $journey = CheckoutJourney::where('order_id', $order->id)->first();
        }
        if ($journey?->order_id === $order->id) {
            return;
        }
        $journey ??= CheckoutJourney::create(['uuid' => (string) Str::uuid(), 'user_id' => $order->user_id, 'apartment_id' => $apartmentId, 'last_seen_at' => now()]);
        $journey->update(['order_id' => $order->id, 'stage' => 'submitted', 'period' => $order->period,
            'payment_method' => $order->payment_method, 'billing_profile_id' => $order->billing_profile_id,
            'quoted_amount' => $order->price, 'last_seen_at' => now(), 'closed_at' => null]);
        $this->event($journey, 'submitted', 'server', ['period' => $order->period, 'payment_method' => $order->payment_method, 'amount' => $order->price, 'billing_profile_id' => $order->billing_profile_id]);
    }

    public function orderEvent(int $orderId, string $type, array $details): void
    {
        foreach (CheckoutJourney::where('order_id', $orderId)->get() as $journey) {
            $this->event($journey, $type, 'server', $details);
        }
    }

    public function validationFailed(User $user, mixed $uuid, array $fields): void
    {
        $journey = is_string($uuid) ? CheckoutJourney::where('uuid', $uuid)->where('user_id', $user->id)->first() : null;
        if ($journey && ! $journey->order_id) {
            $this->event($journey, 'validation_failed', 'server', ['fields' => array_values($fields)]);
        }
    }

    public function billingSaved(User $user, mixed $uuid, BillingProfile $profile): void
    {
        $journey = is_string($uuid) ? CheckoutJourney::where('uuid', $uuid)->where('user_id', $user->id)->first() : null;
        if ($journey && ! $journey->order_id && ! $journey->closed_at) {
            $this->event($journey, 'billing_saved', 'server', ['billing_profile_id' => $profile->id, 'billing_party_type' => $profile->party_type]);
        }
    }
}
