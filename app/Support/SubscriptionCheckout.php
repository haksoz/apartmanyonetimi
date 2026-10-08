<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionCheckout
{
    public function __construct(private PriceQuote $prices) {}

    public function openPending(User $user, Collection $apartments, string $period, string $paymentMethod, BillingProfile $billingProfile, ?string $receiptPath = null, ?string $reference = null): UserSubscription
    {
        if ($apartments->isEmpty()) {
            throw ValidationException::withMessages([
                'apartment_ids' => 'En az bir apartman seçin.',
            ]);
        }

        $billingProfile = BillingProfile::query()->whereKey($billingProfile->id)->first();

        if (! $billingProfile || (int) $billingProfile->user_id !== (int) $user->id || ! $billingProfile->is_active) {
            throw ValidationException::withMessages([
                'billing_profile_id' => 'Seçilen fatura profili kullanılamaz.',
            ]);
        }

        $lines = [];
        $total = 0.0;

        $blocked = SubscriptionItem::query()
            ->whereIn('apartment_id', $apartments->pluck('id'))
            ->where('status', SubscriptionItem::STATUS_PENDING)
            ->first();

        if ($blocked) {
            throw ValidationException::withMessages([
                'apartment_ids' => $blocked->apartment_name.' için bekleyen bir sipariş var.',
            ]);
        }

        foreach ($apartments as $apartment) {
            $quote = $this->prices->forUnits((int) $apartment->unit_count, $period, $apartment);

            if ($quote['requires_quote']) {
                throw ValidationException::withMessages([
                    'apartment_ids' => $apartment->name.': '.$quote['message'],
                ]);
            }

            $lines[] = [$apartment, $quote];
            $total += $quote['amount'];
        }

        $records = [];
        foreach ($apartments as $apartment) {
            $record = Subscription::query()
                ->where('apartment_id', $apartment->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();

            if (! $record) {
                throw ValidationException::withMessages([
                    'apartment_ids' => $apartment->name.' için abonelik kaydı yok.',
                ]);
            }

            $records[$apartment->id] = $record;
        }

        $linkedIds = collect($records)->pluck('id')->unique()->values();

        $subscription = UserSubscription::create([
            'order_number' => $this->orderNumber(),
            'user_id' => $user->id,
            'subscription_id' => $linkedIds->count() === 1 ? $linkedIds->first() : null,
            'package_id' => $this->carrierPackageId(),
            'period' => $period,
            'price' => $total,
            'started_at' => now(),
            'expires_at' => null,
            'is_active' => false,
            'is_trial' => false,
            'status' => UserSubscription::STATUS_PENDING,
            'notes' => 'Apartman kalemlerinden oluşturuldu.',
            'feature_auto_dues' => false,
            'feature_user_portal' => false,
            'feature_reports' => false,
            'feature_multi_apartment' => false,
            'payment_method' => $paymentMethod,
            'receipt_path' => $receiptPath,
            'receipt_reference' => $reference,
        ] + $billingProfile->orderSnapshot());

        foreach ($lines as [$apartment, $quote]) {
            /** @var Apartment $apartment */
            $band = $quote['band'];

            SubscriptionItem::create([
                'apartment_subscription_id' => $records[$apartment->id]->id,
                'subscription_id' => $subscription->id,
                'apartment_id' => $apartment->id,
                'apartment_name' => $apartment->name,
                'unit_count' => (int) $apartment->unit_count,
                'price_band_id' => $band?->id,
                'band_label' => $band?->label ?? 'Teklif',
                'band_min_units' => $band?->min_units ?? (int) $apartment->unit_count,
                'band_max_units' => $band?->max_units,
                'amount' => $quote['amount'],
                'list_amount' => $quote['list_amount'],
                'campaign_name' => $quote['campaign_name'],
                'discount_amount' => $quote['discount_amount'],
                'currency' => 'TRY',
                'plan' => SubscriptionItem::PLAN_PAID,
                'status' => SubscriptionItem::STATUS_PENDING,
                'started_at' => null,
                'expires_at' => null,
                'ended_at' => null,
            ]);
        }

        foreach ($records as $record) {
            $record->update([
                'billing_profile_id' => $billingProfile->id,
            ]);
        }

        return $subscription;
    }

    public function activate(UserSubscription $subscription): void
    {
        $apartmentIds = $subscription->items()->pluck('apartment_id');
        $anchor = SubscriptionItem::query()
            ->whereIn('apartment_id', $apartmentIds)
            ->where('plan', SubscriptionItem::PLAN_PAID)
            ->where('status', SubscriptionItem::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->max('expires_at');

        $startedAt = $anchor ? Carbon::parse($anchor) : now();
        $expiresAt = $subscription->period === 'yearly'
            ? $startedAt->copy()->addYear()
            : $startedAt->copy()->addMonth();

        $this->confirmApartmentSubscriptions($subscription);

        $subscription->update([
            'status' => UserSubscription::STATUS_ACTIVE,
            'is_active' => true,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
        ]);

        $subscription->items()->update([
            'plan' => SubscriptionItem::PLAN_PAID,
            'status' => SubscriptionItem::STATUS_ACTIVE,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'ended_at' => null,
        ]);

        if (! $startedAt->gt(now())) {
            $itemIds = $subscription->items()->pluck('id');

            SubscriptionItem::query()
                ->whereIn('apartment_id', $apartmentIds)
                ->where('status', SubscriptionItem::STATUS_ACTIVE)
                ->whereNotIn('id', $itemIds)
                ->where(function ($query) {
                    $query->where('plan', '!=', SubscriptionItem::PLAN_FREE)
                        ->orWhereNotNull('subscription_id');
                })
                ->update([
                    'status' => SubscriptionItem::STATUS_CANCELLED,
                    'ended_at' => $startedAt,
                ]);
        }

        ApartmentCoverage::sync($apartmentIds);
    }

    private function confirmApartmentSubscriptions(UserSubscription $subscription): void
    {
        $ids = $subscription->items()
            ->whereNotNull('apartment_subscription_id')
            ->pluck('apartment_subscription_id')
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $records = Subscription::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $record = $records->get($id);

            if (! $record) {
                throw ValidationException::withMessages([
                    'subscription' => 'Onaylanacak kalıcı abonelik kaydı bulunamadı.',
                ]);
            }

            $record->update([
                'status' => Subscription::STATUS_ACTIVE,
                'ended_at' => null,
            ]);
        }
    }

    public function grantComplimentary(User $payer, Apartment $apartment, int $months): UserSubscription
    {
        if (SubscriptionItem::query()->where('apartment_id', $apartment->id)->covering()->exists()) {
            throw ValidationException::withMessages([
                'months' => 'Bu apartmanın süren bir ücretli dönemi var.',
            ]);
        }

        $band = $this->prices->bandFor((int) $apartment->unit_count);
        $monthly = null;

        if ($band && ! $band->is_quote && $band->monthly_price !== null) {
            $monthly = (float) $band->monthly_price;
        } elseif ($band?->is_quote && $apartment->custom_monthly_price !== null) {
            $monthly = (float) $apartment->custom_monthly_price;
        }

        $list = $monthly === null ? null : round($monthly * $months, 2);
        $startedAt = now();
        $expiresAt = now()->addMonths($months);

        return DB::transaction(function () use ($payer, $apartment, $band, $list, $startedAt, $expiresAt) {
            $subscription = UserSubscription::create([
                'order_number' => $this->orderNumber(),
                'user_id' => $payer->id,
                'package_id' => $this->carrierPackageId(),
                'period' => UserSubscription::PERIOD_MONTHLY,
                'price' => 0,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'is_trial' => false,
                'status' => UserSubscription::STATUS_ACTIVE,
                'notes' => 'Tanımlı süre',
                'feature_auto_dues' => false,
                'feature_user_portal' => false,
                'feature_reports' => false,
                'feature_multi_apartment' => false,
                'payment_method' => null,
            ]);

            SubscriptionItem::create([
                'subscription_id' => $subscription->id,
                'apartment_id' => $apartment->id,
                'apartment_name' => $apartment->name,
                'unit_count' => (int) $apartment->unit_count,
                'price_band_id' => $band?->id,
                'band_label' => $band?->label ?? 'Teklif',
                'band_min_units' => $band?->min_units ?? (int) $apartment->unit_count,
                'band_max_units' => $band?->max_units,
                'amount' => 0,
                'list_amount' => $list,
                'campaign_name' => 'Tanımlı süre',
                'discount_amount' => $list,
                'currency' => 'TRY',
                'plan' => SubscriptionItem::PLAN_PAID,
                'status' => SubscriptionItem::STATUS_ACTIVE,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'ended_at' => null,
            ]);

            ApartmentCoverage::sync([$apartment->id]);

            return $subscription;
        });
    }

    public function openFree(User $payer, Apartment $apartment): Subscription
    {
        $isOwner = $apartment->members()
            ->where('users.id', $payer->id)
            ->where('apartment_user.role', 'owner')
            ->where('apartment_user.is_active', true)
            ->exists();

        if (! $isOwner) {
            throw ValidationException::withMessages([
                'apartment' => 'Ücretsiz kullanım, apartmanın yöneticisi bağlanmadan açılamaz.',
            ]);
        }

        $startedAt = now();
        $band = $this->prices->bandFor((int) $apartment->unit_count);

        return DB::transaction(function () use ($apartment, $startedAt, $band) {
            $apartmentSubscription = Subscription::create([
                'apartment_id' => $apartment->id,
                'status' => Subscription::STATUS_ACTIVE,
                'started_at' => $startedAt,
                'ended_at' => null,
            ]);

            SubscriptionItem::create([
                'apartment_subscription_id' => $apartmentSubscription->id,
                'subscription_id' => null,
                'apartment_id' => $apartment->id,
                'apartment_name' => $apartment->name,
                'unit_count' => (int) $apartment->unit_count,
                'price_band_id' => $band?->id,
                'band_label' => $band?->label ?? 'Teklif',
                'band_min_units' => $band?->min_units ?? (int) $apartment->unit_count,
                'band_max_units' => $band?->max_units,
                'amount' => 0,
                'currency' => 'TRY',
                'plan' => SubscriptionItem::PLAN_FREE,
                'status' => SubscriptionItem::STATUS_ACTIVE,
                'started_at' => $startedAt,
                'expires_at' => null,
                'ended_at' => null,
            ]);

            return $apartmentSubscription;
        });
    }

    private function carrierPackageId(): int
    {
        $package = Package::query()->firstOrCreate(
            ['slug' => 'hizmet-bedeli'],
            [
                'name' => 'AidatCep Hizmet',
                'description' => 'Apartman kalemi siparişleri için taşıyıcı kayıt. Yetki vermez.',
                'apartment_limit' => 0,
                'monthly_price' => 0,
                'yearly_price' => 0,
                'is_active' => false,
                'show_on_website' => false,
                'sort_order' => 99,
            ]
        );

        return $package->id;
    }

    private function orderNumber(): string
    {
        $year = now()->format('y');

        do {
            $random = strtoupper(substr(uniqid('', true), -6));
            $number = "SIP-{$year}-{$random}";
        } while (UserSubscription::where('order_number', $number)->exists());

        return $number;
    }
}
