<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\BankAccount;
use App\Models\BillingProfile;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentCommercial;
use App\Support\ApartmentCoverage;
use App\Support\PriceQuote;
use App\Support\LegalConsent;
use App\Support\SubscriptionCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriberSubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()
            ->subscriptions()
            ->commercial()
            ->with(['items', 'package'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('subscriber.subscriptions.index', compact('subscriptions'));
    }

    public function create(PriceQuote $prices)
    {
        $apartments = $this->ownedApartments(auth()->user())->get();
        $ids = $apartments->pluck('id');

        $records = Subscription::query()
            ->whereIn('apartment_id', $ids)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->get()
            ->unique('apartment_id')
            ->keyBy('apartment_id');

        $items = SubscriptionItem::query()
            ->with('subscription')
            ->whereIn('apartment_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->groupBy('apartment_id');

        $billingProfiles = auth()->user()
            ->billingProfiles()
            ->where('is_active', true)
            ->orderBy('label')
            ->orderBy('id')
            ->get();
        $activeProfileIds = $billingProfiles->pluck('id');

        $apartments->each(function (Apartment $apartment) use ($prices, $records, $items, $activeProfileIds) {
            $apartment->monthly_quote = $prices->forUnits((int) $apartment->unit_count, 'monthly', $apartment);
            $apartment->yearly_quote = $prices->forUnits((int) $apartment->unit_count, 'yearly', $apartment);
            $apartment->setAttribute('offer', $this->offer(
                $records->get($apartment->id),
                $items->get($apartment->id, collect())
            ));
            $suggested = $records->get($apartment->id)?->billing_profile_id;
            $apartment->setAttribute(
                'suggested_billing_profile_id',
                $activeProfileIds->contains($suggested) ? $suggested : null
            );
        });

        $selectable = $apartments->filter(function (Apartment $apartment) {
            return $apartment->offer['action'] !== 'pending'
                && $apartment->offer['record']
                && (int) $apartment->unit_count <= 100
                && ! ($apartment->monthly_quote['requires_quote'] ?? false);
        })->values();

        $selectedBillingProfileId = old('billing_profile_id');
        if ($selectedBillingProfileId === null && old('billing_mode') !== 'new' && $selectable->count() === 1) {
            $selectedBillingProfileId = $selectable->first()->suggested_billing_profile_id;
        }

        return view('subscriber.subscriptions.create', compact('apartments', 'billingProfiles', 'selectedBillingProfileId'));
    }

    public function store(Request $request, SubscriptionCheckout $checkout)
    {
        $validated = $request->validate([
            'apartment_ids' => ['required', 'array', 'min:1', 'max:1'],
            'apartment_ids.*' => ['integer'],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['required', Rule::in(['havale', 'kredi_kartı'])],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'accept_sales' => ['accepted'],
            'billing_mode' => ['required', Rule::in(['existing', 'new'])],
            'billing_profile_id' => ['required_if:billing_mode,existing', 'nullable', 'integer'],
            'billing_label' => ['required_if:billing_mode,new', 'nullable', 'string', 'max:255'],
            'billing_party_type' => ['required_if:billing_mode,new', 'nullable', 'string', 'max:32', Rule::in(array_keys(BillingProfile::partyTypes()))],
            'billing_legal_name' => ['nullable', 'string', 'max:255'],
            'billing_identity_number' => ['nullable', 'string', 'max:64'],
            'billing_tax_office' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:64'],
            'billing_province' => ['nullable', 'string', 'max:255'],
            'billing_district' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'billing_postal_code' => ['nullable', 'string', 'max:32'],
        ], [
            'apartment_ids.max' => 'Bir siparişte yalnızca bir apartman olabilir.',
            'accept_sales.accepted' => 'Ücretli abonelik için mesafeli satış sözleşmesini ve ön bilgilendirme formunu kabul edin.',
            'billing_mode.required' => 'Fatura profili seçin veya yeni bir profil oluşturun.',
            'billing_profile_id.required_if' => 'Kayıtlı bir fatura profili seçin.',
            'billing_label.required_if' => 'Yeni fatura profili için bir ad girin.',
            'billing_party_type.required_if' => 'Yeni fatura profili için bir tür seçin.',
        ]);

        $user = auth()->user();
        $apartments = $this->ownedApartments($user)
            ->whereIn('id', $validated['apartment_ids'])
            ->get();

        if ($apartments->count() !== count($validated['apartment_ids'])) {
            throw ValidationException::withMessages([
                'apartment_ids' => 'Seçilen apartmanlardan biri size ait değil.',
            ]);
        }

        $oversized = $apartments->first(fn (Apartment $apartment) => (int) $apartment->unit_count > ApartmentCommercial::FREE_UNIT_LIMIT);

        if ($oversized) {
            throw ValidationException::withMessages([
                'apartment_ids' => $oversized->name.' için özel teklif gerekir. Sipariş bu ekrandan açılamaz.',
            ]);
        }

        if ($validated['payment_method'] === 'kredi_kartı' && ($request->hasFile('receipt') || filled($validated['reference_code'] ?? null))) {
            throw ValidationException::withMessages([
                'payment_method' => 'Kredi kartı siparişine dekont veya referans eklenemez.',
            ]);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("receipts/{$user->id}", 'public');
        }

        $subscription = DB::transaction(function () use ($checkout, $user, $apartments, $validated, $receiptPath) {
            $billingProfile = $this->billingProfileFor($user, $validated);

            return $checkout->openPending(
                $user,
                $apartments,
                $validated['period'],
                $validated['payment_method'],
                $billingProfile,
                $receiptPath,
                $validated['reference_code'] ?? null,
            );
        });

        app(LegalConsent::class)->recordSale($user, $subscription, $request);

        $message = $validated['payment_method'] === 'havale'
            ? 'Siparişiniz alındı. Havale/EFT ödemesi için banka bilgilerini görüntüleyebilirsiniz.'
            : 'Siparişiniz alındı. Kredi kartı ödeme altyapısı entegre edildiğinde buradan ödemenizi tamamlayabileceksiniz.';

        return redirect()->route('subscriber.subscriptions.receipt', $subscription)
            ->with('status', $message);
    }

    public function receipt(UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        $subscription->load(['items.apartment', 'items.apartmentSubscription', 'package']);
        $accounts = BankAccount::active()->ordered()->get();

        return view('subscriber.subscriptions.receipt', compact('subscription', 'accounts'));
    }

    public function paymentInfo(Request $request, UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        try {
            if (! $subscription->isPending()) {
                throw ValidationException::withMessages([
                    'payment_info' => 'Ödeme bilgisi yalnızca bekleyen siparişe eklenebilir.',
                ]);
            }

            if ($subscription->payment_method === 'kredi_kartı') {
                throw ValidationException::withMessages([
                    'payment_info' => 'Kredi kartı siparişine dekont veya referans eklenemez.',
                ]);
            }

            if ($subscription->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment_info' => 'Ödeme bilgisi alındı. Dekont veya referans artık değiştirilemez.',
                ]);
            }

            $validated = $request->validate([
                'reference_code' => ['nullable', 'string', 'max:255'],
                'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            ]);

            if (! filled($validated['reference_code'] ?? null) && ! $request->hasFile('receipt')) {
                throw ValidationException::withMessages([
                    'payment_info' => 'Referans numarası veya dekont dosyası alanlarından en az biri doldurulmalıdır.',
                ]);
            }
        } catch (ValidationException $exception) {
            session()->flash('payment_modal', $subscription->id);

            throw $exception;
        }

        $data = [];

        if (filled($validated['reference_code'] ?? null)) {
            $data['receipt_reference'] = $validated['reference_code'];
        }

        if ($request->hasFile('receipt')) {
            $data['receipt_path'] = $request->file('receipt')->store('receipts/' . auth()->id(), 'public');
        }

        $subscription->update($data);

        return back()->with('status', 'Ödeme bilgileriniz başarıyla kaydedildi.');
    }

    public function cancel(UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        if (! $subscription->isPending()) {
            throw ValidationException::withMessages([
                'subscription' => 'Yalnızca ödeme bekleyen sipariş iptal edilebilir.',
            ]);
        }

        if ($subscription->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'subscription' => 'Ödeme bilgisi gönderildiği için sipariş iptal edilemez.',
            ]);
        }

        $apartmentIds = $subscription->items()->pluck('apartment_id');

        $subscription->items()->update([
            'status' => SubscriptionItem::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);

        $subscription->update([
            'status' => UserSubscription::STATUS_CANCELLED,
            'cancelled_by' => UserSubscription::CANCELLED_BY_CUSTOMER,
            'is_active' => false,
            'ended_at' => now(),
        ]);

        ApartmentCoverage::sync($apartmentIds);

        return redirect()
            ->route('subscriber.subscriptions.create', ['type' => 'renew'])
            ->with('status', 'Sipariş iptal edildi. Apartman için yeniden sipariş oluşturabilirsiniz.');
    }

    /**
     * @param  Collection<int, SubscriptionItem>  $items
     * @return array{action: string, record: ?Subscription, period: ?SubscriptionItem, order: ?UserSubscription}
     */
    private function offer(?Subscription $record, Collection $items): array
    {
        $pending = $items->first(function (SubscriptionItem $item) {
            return $item->status === SubscriptionItem::STATUS_PENDING
                && $item->subscription?->status === UserSubscription::STATUS_PENDING;
        });

        if ($pending) {
            return [
                'action' => 'pending',
                'record' => $record,
                'period' => null,
                'order' => $pending->subscription,
            ];
        }

        $covering = $items->first(fn (SubscriptionItem $item) => $item->isCovering());

        if ($covering) {
            return [
                'action' => 'renew',
                'record' => $record,
                'period' => $covering,
                'order' => null,
            ];
        }

        $expired = $items->first(function (SubscriptionItem $item) {
            return $item->plan === SubscriptionItem::PLAN_PAID
                && $item->expires_at !== null
                && $item->expires_at->lt(now())
                && $item->status !== SubscriptionItem::STATUS_PENDING;
        });

        if ($expired) {
            return [
                'action' => 'restart',
                'record' => $record,
                'period' => $expired,
                'order' => null,
            ];
        }

        return [
            'action' => 'upgrade',
            'record' => $record,
            'period' => null,
            'order' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function billingProfileFor(User $user, array $validated): BillingProfile
    {
        if ($validated['billing_mode'] === 'new') {
            $attributes = [
                'label' => $validated['billing_label'],
                'party_type' => $validated['billing_party_type'],
                'is_active' => true,
            ];

            foreach ([
                'legal_name' => 'billing_legal_name',
                'identity_number' => 'billing_identity_number',
                'tax_office' => 'billing_tax_office',
                'email' => 'billing_email',
                'phone' => 'billing_phone',
                'country' => 'billing_country',
                'province' => 'billing_province',
                'district' => 'billing_district',
                'address' => 'billing_address',
                'postal_code' => 'billing_postal_code',
            ] as $column => $input) {
                $value = $validated[$input] ?? null;
                $attributes[$column] = $value === '' ? null : $value;
            }

            return $user->billingProfiles()->create($attributes);
        }

        $profile = BillingProfile::query()
            ->whereKey($validated['billing_profile_id'] ?? null)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'billing_profile_id' => 'Seçilen fatura profili kullanılamaz.',
            ]);
        }

        return $profile;
    }

    private function authorizeSubscription(UserSubscription $subscription): void
    {
        if ($subscription->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function ownedApartments(User $user)
    {
        return Apartment::query()
            ->where('is_active', true)
            ->whereHas('members', function ($query) use ($user) {
                $query->whereKey($user->id)
                    ->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            })
            ->orderBy('name');
    }
}
