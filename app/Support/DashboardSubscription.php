<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;

class DashboardSubscription
{
    public function __construct(private SubscriberApartmentOverview $overview) {}

    public function forApartment(Apartment $apartment, User $user): array
    {
        $coverage = $this->overview->coverageFor($apartment);
        $end = $coverage['end'];
        $days = $end ? max(0, (int) now('Europe/Istanbul')->startOfDay()
            ->diffInDays($end->copy()->timezone('Europe/Istanbul')->startOfDay(), false)) : null;
        $active = $coverage['active'];
        $pending = $coverage['pending']?->subscription;
        $state = $active ? 'paid' : ($coverage['expired'] ? 'expired' : 'free');

        return [
            'state' => $state,
            'end' => $end?->copy()->timezone('Europe/Istanbul'),
            'days' => $days,
            'expiring' => $active && $days !== null && $days <= 5,
            'pending' => $pending !== null,
            'pending_url' => $pending && (int) $pending->user_id === $user->id
                ? route('subscriber.subscriptions.receipt', $pending) : null,
            'purchase_url' => route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id, 'from' => 'dashboard']),
            'details_url' => route('subscriber.apartments.show', $apartment),
        ];
    }
}
