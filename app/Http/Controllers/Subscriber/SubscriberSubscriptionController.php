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
            ->numberedOrder()
            ->whereNull('checkout_abandoned_at')
            ->with(['items', 'package'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('subscriber.subscriptions.index', compact('subscriptions'));
    }

    public function create(Request $request, PriceQuote $prices)
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

        $requestedId = (int) old('apartment_ids.0', $request->integer('apartment_id'));
        $candidate = $requestedId ? $apartments->firstWhere('id', $requestedId) : ($apartments->count() === 1 ? $apartments->first() : null);
        if ($candidate?->offer['action'] === 'pending' && (int) $candidate->offer['order']->user_id === (int) auth()->id()) {
            session(['subscription_return.'.$candidate->offer['order']->id => $request->input('from') === 'dashboard' ? 'dashboard' : 'subscriber']);
            return redirect()->route('subscriber.subscriptions.receipt', array_filter([
                'subscription' => $candidate->offer['order'],
                'from' => $request->input('from') === 'dashboard' ? 'dashboard' : null,
            ]));
        }
        $selectedApartmentId = $candidate ? $selectable->firstWhere('id', $candidate->id)?->id : null;
        $selectedBillingProfileId = old('billing_profile_id');
        if ($selectedBillingProfileId === null && old('billing_mode') !== 'new' && ($selectedApartmentId || $selectable->count() === 1)) {
            $selectedBillingProfileId = ($selectable->firstWhere('id', $selectedApartmentId) ?? $selectable->first())->suggested_billing_profile_id;
        }

        $checkoutApartment = $apartments->firstWhere('id', $selectedApartmentId);
        $quoteSignature = $checkoutApartment ? app(\App\Support\CheckoutQuote::class)->signature($request->user(), $checkoutApartment) : null;
        $coverageEnd = $checkoutApartment ? app(\App\Support\SubscriberApartmentOverview::class)->coverageFor($checkoutApartment)['end'] : null;

        $checkoutJourney = $checkoutApartment
            ? app(\App\Support\CheckoutJourneyTracking::class)->begin($request->user(), $checkoutApartment, old('checkout_journey'))
            : null;

        return view('subscriber.subscriptions.create', compact('apartments', 'billingProfiles', 'selectedBillingProfileId', 'selectedApartmentId', 'checkoutApartment', 'quoteSignature', 'coverageEnd', 'checkoutJourney'));
    }

    public function store(Request $request, SubscriptionCheckout $checkout)
    {
        try {
            if ($request->input('payment_method') === 'kredi_kartı') {
                // Client fields are not authoritative; checkout always uses the customer's profile.
                $request->merge(\App\Support\Paytr\PayerContact::fromUser($request->user()->fresh()));
            }
            $validated = $request->validate([
                'apartment_ids' => ['required', 'array', 'min:1', 'max:1'],
                'apartment_ids.*' => ['integer'],
                'period' => ['required', Rule::in(['monthly', 'yearly'])],
                'payment_method' => ['required', Rule::in(app(\App\Support\CheckoutPaymentMethods::class)->forUser(auth()->user()))],
                'checkout_token' => ['required_if:payment_method,kredi_kartı', 'nullable', 'uuid'],
                'checkout_journey' => ['nullable', 'uuid'],
                'quote_signature' => ['required_if:payment_method,kredi_kartı', 'nullable', 'string', 'size:64'],
                'payer_name' => ['required_if:payment_method,kredi_kartı', 'nullable', 'string', 'max:60'],
                'payer_email' => ['required_if:payment_method,kredi_kartı', 'nullable', 'email', 'max:100', 'regex:/^[\x00-\x7F]+$/'],
                'payer_phone' => ['required_if:payment_method,kredi_kartı', 'nullable', 'string', 'regex:/^[1-9][0-9]{9}$/D'],
                'payer_address' => ['required_if:payment_method,kredi_kartı', 'nullable', 'string', 'max:400'],
                'reference_code' => ['nullable', 'string', 'max:255'],
                'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
                'accept_sales' => ['accepted'],
                'billing_mode' => ['required', Rule::in(['existing', 'new'])],
                'billing_profile_id' => ['required_if:billing_mode,existing', 'nullable', 'integer'],
                'billing_label' => ['required_if:billing_mode,new', 'nullable', 'string', 'max:255'],
                'billing_party_type' => ['required_if:billing_mode,new', 'nullable', 'string', 'max:32', Rule::in(array_keys(BillingProfile::partyTypes()))],
                'billing_legal_name' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('billing_mode') === 'new' && in_array($request->input('billing_party_type'), [BillingProfile::TYPE_INDIVIDUAL, BillingProfile::TYPE_CORPORATE], true))],
                'billing_identity_number' => ['nullable', 'string', 'max:64', Rule::requiredIf(fn () => $request->input('billing_mode') === 'new' && in_array($request->input('billing_party_type'), [BillingProfile::TYPE_INDIVIDUAL, BillingProfile::TYPE_CORPORATE], true))],
                'billing_tax_office' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('billing_mode') === 'new' && $request->input('billing_party_type') === BillingProfile::TYPE_CORPORATE)],
                'billing_email' => ['nullable', 'email', 'max:255'],
                'billing_phone' => ['nullable', 'string', 'max:255'],
                'billing_country' => ['nullable', 'string', 'max:64'],
                'billing_province' => ['nullable', 'string', 'max:255'],
                'billing_district' => ['nullable', 'string', 'max:255'],
                'billing_address' => ['nullable', 'string', 'max:1000', Rule::requiredIf(fn () => $request->input('billing_mode') === 'new' && in_array($request->input('billing_party_type'), [BillingProfile::TYPE_INDIVIDUAL, BillingProfile::TYPE_CORPORATE], true))],
                'billing_postal_code' => ['nullable', 'string', 'max:32'],
            ], [
                'payment_method.in' => 'Seçilen ödeme yöntemi hesabınız için kullanılamıyor. Ödeme seçeneklerini yeniden kontrol edin.',
                'payer_phone.regex' => 'Telefonu başında 0 olmadan 10 hane girin. Örnek: 5551234567.',
                'payer_name.required' => 'Ad soyad bilginizi Hesabım sayfasından tamamlayın.',
                'payer_email.required' => 'Müşteri profilinizde e-posta bilgisi bulunmuyor. Destek ekibiyle iletişime geçin.',
                'payer_phone.required' => 'Telefon bilginizi Hesabım sayfasından tamamlayın.',
                'payer_address.required' => 'İletişim adresinizi Hesabım sayfasından tamamlayın.',
                'payer_email.email' => 'Geçerli bir e-posta adresi girin.',
                'payer_email.regex' => 'Ödeme e-posta adresinde Türkçe karakter kullanmayın.',
                'apartment_ids.max' => 'Bir siparişte yalnızca bir apartman olabilir.',
                'accept_sales.accepted' => 'Ücretli abonelik için mesafeli satış sözleşmesini ve ön bilgilendirme formunu kabul edin.',
                'billing_mode.required' => 'Fatura profilini seçin veya yeni bir profil oluşturun.',
                'billing_profile_id.required_if' => 'Kayıtlı bir fatura profili seçin.',
                'billing_label.required_if' => 'Profil adı girin.',
                'billing_party_type.required_if' => 'Bireysel veya kurumsal türü seçin.',
                'billing_legal_name.required' => 'Fatura adı veya şirket adı girin.',
                'billing_address.required' => 'Fatura adresi girin.',
                'billing_identity_number.required' => 'Kimlik numarası veya vergi numarası girin.',
                'billing_tax_office.required' => 'Vergi dairesi girin.',
            ]);
        } catch (ValidationException $exception) {
            app(\App\Support\CheckoutJourneyTracking::class)->validationFailed($request->user(), $request->input('checkout_journey'), array_keys($exception->errors()));
            throw $exception;
        }

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

        $fingerprintData = \Illuminate\Support\Arr::except($validated, ['checkout_token', 'quote_signature', 'receipt', 'checkout_journey']);
        $fingerprintData['receipt_hash'] = $request->hasFile('receipt') ? hash_file('sha256', $request->file('receipt')->getRealPath()) : null;
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));
        $receiptPath = null;
        try {
            $subscription = DB::transaction(function () use ($checkout, $request, $user, $apartments, $validated, $fingerprint, &$receiptPath) {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($validated['payment_method'] === 'kredi_kartı') {
                    $validated = array_replace($validated, \App\Support\Paytr\PayerContact::validated($lockedUser));
                }
                if ($validated['payment_method'] === 'havale' && ! $lockedUser->bank_transfer_allowed) {
                    throw ValidationException::withMessages(['payment_method' => 'Havale / EFT ile ödeme izniniz bulunmuyor.']);
                }
                $token = $validated['checkout_token'] ?? null;
                if ($token && ($replay = UserSubscription::withTrashed()->where('checkout_token', $token)->first())) {
                    if ((int) $replay->user_id !== $user->id || $replay->checkout_fingerprint !== $fingerprint || $replay->trashed()) {
                        throw ValidationException::withMessages(['checkout_token' => 'Bu işlem anahtarı farklı veya silinmiş bir siparişe ait. Sayfayı yenileyin.']);
                    }
                    if ($replay->checkout_abandoned_at) {
                        throw ValidationException::withMessages(['checkout_token' => 'Bu ödeme girişimi kapatıldı. İşlemi yeniden başlatın.']);
                    }
                    return $replay;
                }

                $locked = $this->ownedApartments($user)->whereIn('id', $apartments->pluck('id'))->orderBy('id')->lockForUpdate()->get();
                if ($locked->count() !== 1) {
                    throw ValidationException::withMessages(['apartment_ids' => 'Apartman yönetim yetkiniz değişti.']);
                }
                $apartment = $locked->first();
                if (! empty($validated['checkout_journey'])) {
                    $journey = \App\Models\CheckoutJourney::where('uuid', $validated['checkout_journey'])
                        ->where('user_id', $user->id)->where('apartment_id', $apartment->id)->lockForUpdate()->first();
                    if ($journey?->isFormTimedOut() || $journey?->closed_at) {
                        throw ValidationException::withMessages(['checkout_journey' => 'Formun süresi doldu. Süreyi uzatarak devam edin.']);
                    }
                }
                $pending = $apartment->subscriptionItems()->where('status', SubscriptionItem::STATUS_PENDING)
                    ->whereHas('subscription', fn ($query) => $query->where('status', UserSubscription::STATUS_PENDING))
                    ->with('subscription')->first();
                if ($pending && $pending->subscription->payment_method !== 'kredi_kartı') {
                    if ($pending->subscription->checkout_abandoned_at) {
                        throw ValidationException::withMessages(['payment_method' => 'Önceki ödeme girişiminin sonucu henüz kesinleşmedi. Yeni tahsilat başlamadan önce sonuç doğrulanmalıdır. Lütfen daha sonra tekrar deneyin.']);
                    }
                    if ((int) $pending->subscription->user_id !== $user->id) {
                        throw ValidationException::withMessages(['apartment_ids' => 'Bu apartman için başka bir kullanıcının bekleyen siparişi bulunuyor.']);
                    }
                    return $pending->subscription;
                }
                if (filled($validated['quote_signature'] ?? null) && ! hash_equals(
                    app(\App\Support\CheckoutQuote::class)->signature($user, $apartment), $validated['quote_signature']
                )) {
                    throw ValidationException::withMessages(['quote_signature' => 'Fiyat, vergi veya sözleşme bilgisi değişti. Güncel tutarı ve sözleşmeleri yeniden kontrol edip onaylayın.']);
                }
                $billingProfile = $this->billingProfileFor($user, $validated);
                if ($request->hasFile('receipt')) {
                    $receiptPath = $request->file('receipt')->store("receipts/{$user->id}", 'public');
                }
                $order = $checkout->openPending($user, $locked, $validated['period'], $validated['payment_method'], $billingProfile, $receiptPath, $validated['reference_code'] ?? null);
                $order->update(['checkout_token' => $token, 'checkout_fingerprint' => $fingerprint]);
                app(\App\Support\CheckoutJourneyTracking::class)->attach($order, $validated['checkout_journey'] ?? null);
                app(LegalConsent::class)->recordSale($user, $order, $request);
                app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($order->id, 'sale_accepted', []);
                $attempt = app(\App\Support\SubscriptionPaymentAttempts::class)->open($order);
                if ($validated['payment_method'] === 'kredi_kartı') {
                    $attempt->update(['payer_snapshot' => \Illuminate\Support\Arr::only($validated, ['payer_name', 'payer_email', 'payer_phone', 'payer_address'])]);
                }

                return $order;
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof ValidationException) {
                app(\App\Support\CheckoutJourneyTracking::class)->validationFailed($request->user(), $request->input('checkout_journey'), array_keys($exception->errors()));
            }
            if ($receiptPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($receiptPath);
            }
            throw $exception;
        }

        $message = ! $subscription->wasRecentlyCreated ? 'Mevcut siparişinizi görüntülüyorsunuz.' : ($validated['payment_method'] === 'havale'
            ? 'Siparişiniz alındı. Havale/EFT ödemesi için banka bilgilerini görüntüleyebilirsiniz.'
            : 'Siparişiniz alındı. Güvenli kart ödeme ekranına geçebilirsiniz.');

        session(['subscription_return.'.$subscription->id => $request->input('checkout_return') === 'dashboard' ? 'dashboard' : 'subscriber']);
        $receiptParameters = array_filter(['subscription' => $subscription,
            'from' => $request->input('checkout_return') === 'dashboard' ? 'dashboard' : null]);
        if ($subscription->payment_method === 'kredi_kartı' && $subscription->isPending()) {
            try {
                app(\App\Support\Paytr\StartIframe::class)->start($subscription, (string) $request->ip());

                return redirect()->route('subscriber.subscriptions.card', $subscription);
            } catch (ValidationException $exception) {
                return redirect()->route('subscriber.subscriptions.receipt', $receiptParameters)->withErrors($exception->errors());
            }
        }

        return redirect()->route('subscriber.subscriptions.receipt', $receiptParameters)
            ->with('status', $message);
    }

    public function receipt(Request $request, UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);
        if ($subscription->checkout_abandoned_at) {
            return redirect()->route('subscriber.subscriptions.create', ['apartment_id' => $subscription->items()->value('apartment_id')]);
        }

        $subscription->load(['items.apartment', 'items.apartmentSubscription', 'package', 'legalAcceptances.legalDocumentVersion']);
        $cardPaymentState = $subscription->payment_method === 'kredi_kartı'
            ? app(\App\Support\Paytr\PaymentState::class)->forOrder($subscription) : null;
        $accounts = BankAccount::active()->ordered()->get();

        $summaryMode = $request->query('from') === 'dashboard';

        return view('subscriber.subscriptions.receipt', compact('subscription', 'accounts', 'cardPaymentState', 'summaryMode'));
    }

    public function paymentInfo(Request $request, UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        return DB::transaction(function () use ($request, $subscription) {
            return $this->savePaymentInfo($request, \App\Support\SubscriptionOrderLock::acquire($subscription));
        });
    }

    private function savePaymentInfo(Request $request, UserSubscription $subscription)
    {
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
        app(\App\Support\CheckoutJourneyTracking::class)->orderEvent($subscription->id, 'payment_proof', ['receipt_received' => (bool) $subscription->receipt_path, 'reference_received' => filled($subscription->receipt_reference)]);

        return back()->with('status', 'Ödeme bilgileriniz başarıyla kaydedildi.');
    }

    public function cancel(UserSubscription $subscription)
    {
        $this->authorizeSubscription($subscription);

        app(\App\Support\CancelSubscriptionOrder::class)->cancel($subscription, UserSubscription::CANCELLED_BY_CUSTOMER);

        return redirect()
            ->route('subscriber.subscriptions.create')
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
                && $item->subscription?->status === UserSubscription::STATUS_PENDING
                && $item->subscription->payment_method !== 'kredi_kartı'
                && ! $item->subscription->checkout_abandoned_at;
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
