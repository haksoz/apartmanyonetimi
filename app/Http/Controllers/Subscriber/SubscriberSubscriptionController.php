<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\BankAccount;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\ApartmentCoverage;
use App\Support\PriceQuote;
use App\Support\SubscriptionCheckout;
use Illuminate\Http\Request;
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

        $apartments->each(function (Apartment $apartment) use ($prices, $records, $items) {
            $apartment->monthly_quote = $prices->forUnits((int) $apartment->unit_count, 'monthly', $apartment);
            $apartment->yearly_quote = $prices->forUnits((int) $apartment->unit_count, 'yearly', $apartment);
            $apartment->setAttribute('offer', $this->offer(
                $records->get($apartment->id),
                $items->get($apartment->id, collect())
            ));
        });

        return view('subscriber.subscriptions.create', compact('apartments'));
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
        ], [
            'apartment_ids.max' => 'Bir siparişte yalnızca bir apartman olabilir.',
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

        if ($validated['payment_method'] === 'kredi_kartı' && ($request->hasFile('receipt') || filled($validated['reference_code'] ?? null))) {
            throw ValidationException::withMessages([
                'payment_method' => 'Kredi kartı siparişine dekont veya referans eklenemez.',
            ]);
        }

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("receipts/{$user->id}", 'public');
        }

        $subscription = $checkout->openPending(
            $user,
            $apartments,
            $validated['period'],
            $validated['payment_method'],
            $receiptPath,
            $validated['reference_code'] ?? null,
        );

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
