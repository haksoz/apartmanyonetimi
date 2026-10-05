<?php

namespace App\Http\Controllers\Subscriber;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Models\BankAccount;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\UserSubscription;
use App\Support\PriceQuote;
use App\Support\SubscriptionCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriberSubscriptionController extends Controller
{
    public function index()
    {
        $subscriptions = auth()->user()
            ->subscriptions()
            ->with(['items', 'package'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('subscriber.subscriptions.index', compact('subscriptions'));
    }

    public function create(PriceQuote $prices)
    {
        $apartments = $this->ownedApartments(auth()->user())
            ->get()
            ->map(function (Apartment $apartment) use ($prices) {
                $apartment->monthly_quote = $prices->forUnits((int) $apartment->unit_count, 'monthly', $apartment);
                $apartment->yearly_quote = $prices->forUnits((int) $apartment->unit_count, 'yearly', $apartment);
                $apartment->setAttribute(
                    'commercial_covered',
                    SubscriptionItem::query()->where('apartment_id', $apartment->id)->covering()->exists()
                );

                return $apartment;
            });

        return view('subscriber.subscriptions.create', compact('apartments'));
    }

    public function store(Request $request, SubscriptionCheckout $checkout)
    {
        $validated = $request->validate([
            'apartment_ids' => ['required', 'array', 'min:1'],
            'apartment_ids.*' => ['integer'],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'payment_method' => ['required', Rule::in(['havale', 'kredi_kartı'])],
            'reference_code' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
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

        $accounts = BankAccount::active()->ordered()->get();
        $subscription->load('items');

        return view('subscriber.subscriptions.receipt', compact('subscription', 'accounts'));
    }

    public function paymentInfo(Request $request, UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        $validated = $request->validate([
            'reference_code' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        if (empty($validated['reference_code']) && ! $request->hasFile('receipt')) {
            throw ValidationException::withMessages([
                'payment_info' => 'Referans numarası veya dekont dosyası alanlarından en az biri doldurulmalıdır.',
            ]);
        }

        $data = [];

        if (! empty($validated['reference_code'])) {
            $data['receipt_reference'] = $validated['reference_code'];
        }

        if ($request->hasFile('receipt')) {
            if ($subscription->receipt_path) {
                Storage::disk('public')->delete($subscription->receipt_path);
            }
            $data['receipt_path'] = $request->file('receipt')->store('receipts/' . auth()->id(), 'public');
        }

        $subscription->update($data);

        return back()->with('status', 'Ödeme bilgileriniz başarıyla kaydedildi.');
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
