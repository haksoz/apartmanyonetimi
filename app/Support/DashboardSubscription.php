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
        $pendingMessage = null;
        $pendingLinkLabel = 'Siparişi görüntüle';
        $pendingUrl = $pending && (int) $pending->user_id === $user->id
            ? route('subscriber.subscriptions.receipt', $pending) : null;
        if ($pending?->payment_method === 'kredi_kartı') {
            $paymentState = app(\App\Support\Paytr\PaymentState::class)->forOrder($pending);
            $pendingMessage = in_array($paymentState['state'], ['ready', 'failed'], true)
                ? 'Kart ödemeniz henüz tamamlanmadı.' : $paymentState['message'];
            $pendingLinkLabel = 'Ödeme durumunu görüntüle';
            if ($pendingUrl) {
                $pendingUrl = route('subscriber.subscriptions.card-result', $pending);
            }
        } elseif ($pending) {
            $pendingMessage = $pending->hasPaymentProof()
                ? 'Ödeme bilgileriniz alındı, yönetici onayı bekleniyor.' : 'Abonelik siparişiniz ödeme bekliyor.';
        }

        return [
            'state' => $state,
            'end' => $end?->copy()->timezone('Europe/Istanbul'),
            'days' => $days,
            'expiring' => $active && $days !== null && $days <= 5,
            'pending' => $pending !== null,
            'pending_url' => $pendingUrl,
            'pending_message' => $pendingMessage,
            'pending_link_label' => $pendingLinkLabel,
            'purchase_url' => route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id, 'from' => 'dashboard']),
            'details_url' => route('subscriber.apartments.show', $apartment),
        ];
    }
}
