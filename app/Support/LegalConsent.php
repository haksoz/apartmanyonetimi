<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class LegalConsent
{
    public const MEMBERSHIP = 'membership';

    public const DISTANCE_SALES = 'distance_sales';

    public const PRE_INFORMATION = 'pre_information';

    public const RESIDENT_DATA = 'resident_data';

    public const PRIVACY = 'privacy';

    public const VERSIONS = [
        self::MEMBERSHIP => '1',
        self::DISTANCE_SALES => '1',
        self::PRE_INFORMATION => '1',
        self::RESIDENT_DATA => '1',
        self::PRIVACY => '1',
    ];

    public function saleRules(): array
    {
        return [
            'accept_sales' => ['accepted'],
        ];
    }

    public function saleMessages(): array
    {
        return [
            'accept_sales.accepted' => 'Ücretli abonelik için mesafeli satış sözleşmesini ve ön bilgilendirme formunu kabul edin.',
        ];
    }

    public function residentDataRules(): array
    {
        return [
            'accept_resident_data' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ];
    }

    public function residentDataMessages(): array
    {
        return [
            'accept_resident_data.accepted' => 'Apartman açmak için daire sakini verisi bildirimini kabul edin.',
            'accept_privacy.accepted' => 'Apartman açmak için gizlilik ve KVKK aydınlatmasını kabul edin.',
        ];
    }

    public function requireForApartment(Request $request, bool $sale): void
    {
        $rules = $this->residentDataRules();
        $messages = $this->residentDataMessages();

        if ($sale) {
            $rules = array_merge($rules, $this->saleRules());
            $messages = array_merge($messages, $this->saleMessages());
        }

        $request->validate($rules, $messages);
    }

    public function recordMembership(User $user, Request $request): void
    {
        $this->record($user, self::MEMBERSHIP, null, null, $request);
    }

    public function recordPrivacy(User $user, Request $request): void
    {
        $this->record($user, self::PRIVACY, null, null, $request);
    }

    public function recordResidentData(User $user, Apartment $apartment, Request $request): void
    {
        $this->record($user, self::RESIDENT_DATA, null, $apartment->id, $request);
        $this->record($user, self::PRIVACY, null, $apartment->id, $request);
    }

    public function recordSale(User $user, UserSubscription $order, Request $request): void
    {
        $this->record($user, self::DISTANCE_SALES, $order->id, null, $request);
        $this->record($user, self::PRE_INFORMATION, $order->id, null, $request);
    }

    private function record(User $user, string $key, ?int $orderId, ?int $apartmentId, Request $request): void
    {
        LegalAcceptance::create([
            'user_id' => $user->id,
            'user_subscription_id' => $orderId,
            'apartment_id' => $apartmentId,
            'document_key' => $key,
            'document_version' => self::VERSIONS[$key],
            'accepted_at' => now(),
            'ip_address' => $request->ip(),
        ]);
    }
}
