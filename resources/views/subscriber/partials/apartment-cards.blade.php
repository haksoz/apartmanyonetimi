        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($apartments as $apartment)
                @php
                    $active = $apartment->commercial_active;
                    $pending = $apartment->commercial_pending;
                    $expired = $apartment->commercial_expired;
                    $monthly = $apartment->monthly_quote;
                    $yearly = $apartment->yearly_quote;
                    $ownActive = $active && (int) $active->subscription->user_id === (int) auth()->id();
                    $ownPending = $pending && (int) $pending->subscription->user_id === (int) auth()->id();
                    $needsQuote = ($monthly['requires_quote'] ?? false) && ! $active;
                    $quoteScale = (int) $apartment->unit_count > 100;
                    $canPurchase = (bool) $apartment->can_purchase;
                    $paidUntil = $apartment->commercial_coverage_end ?? $active?->expires_at;
                    $renewSoon = $active && $paidUntil && $paidUntil->copy()->startOfDay()->lessThanOrEqualTo(now()->startOfDay()->addDays(7));
                    $daysLeft = $renewSoon ? (int) now()->startOfDay()->diffInDays($paidUntil->copy()->startOfDay()) : null;
                    $proofSent = $ownPending && $pending->subscription?->hasPaymentProof();
                    $showPay = $canPurchase && ! $quoteScale && ! $needsQuote && ! $pending;
                    $payLabel = ($active || $expired) ? 'Yenile' : 'Ücretliye Geç';
                    $place = collect([$apartment->district, $apartment->province])->filter()->implode(' / ');
                    if ($place === '' && $apartment->address) {
                        $place = \Illuminate\Support\Str::limit($apartment->address, 48);
                    }
                    $periodLabel = $active?->subscription?->notes === 'Tanımlı süre'
                        ? 'Tanımlı süre'
                        : ($active?->subscription?->period === 'yearly' ? 'Yıllık' : 'Aylık');
                    if ($active) {
                        $statusLabel = 'AidatCep Ücretli Kullanım';
                        $statusClass = 'bg-emerald-50 text-emerald-800';
                    } elseif ($quoteScale) {
                        $statusLabel = 'Özel Teklif';
                        $statusClass = 'bg-slate-100 text-slate-700';
                    } elseif ($expired) {
                        $statusLabel = 'Ücretli Kullanım Sona Erdi';
                        $statusClass = 'bg-red-50 text-red-700';
                    } else {
                        $statusLabel = 'AidatCep Ücretsiz Kullanım';
                        $statusClass = 'bg-slate-100 text-slate-700';
                    }
                @endphp
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-950">{{ $apartment->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $apartment->unit_count }} daire{{ $place !== '' ? ' · '.$place : '' }}</p>
                        </div>
                        <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="mt-4 flex-1 space-y-1 text-sm text-slate-600">
                        @if ($quoteScale && ! $active && ! $pending)
                            <p class="font-medium text-slate-800">Özel Teklif</p>
                            <p>{{ \App\Support\ApartmentCommercial::QUOTE_MESSAGE }}</p>
                        @elseif ($pending && ! $active && $ownPending && $proofSent)
                            <p>AidatCep Temel yönetim açık. (Ücretsiz Abonelik)</p>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.
                                <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-1 block underline">Sipariş Detayları</a>
                            </p>
                        @elseif ($pending && ! $active && $ownPending)
                            <p>AidatCep Temel yönetim açık. (Ücretsiz Abonelik)</p>
                            <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                {{ $pending->subscription?->order_number ?: '—' }} numaralı ücretli kullanım siparişiniz oluşturuldu. Ödemeniz bekleniyor.
                                Ödeme dekontunuzu <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="underline">Sipariş Detaylarından</a> yollayabilirsiniz.
                            </p>
                        @elseif ($pending && ! $active)
                            <p>AidatCep Temel yönetim açık. (Ücretsiz Abonelik)</p>
                            <p>Ödeme {{ $pending->subscription->user?->name }} siparişinde bekliyor.</p>
                        @elseif ($active && ! $ownActive)
                            <p>Bu hizmet {{ $apartment->name }} için {{ $active->subscription->user?->name }} tarafından satın alınmıştır.</p>
                            <p>Paket: {{ $periodLabel }}</p>
                            @if ($active->started_at)
                                <p>Başlangıç {{ $active->started_at->format('d.m.Y') }}</p>
                            @endif
                            <p>Bitiş {{ $paidUntil?->format('d.m.Y') ?? 'süresiz' }}</p>
                        @elseif ($active)
                            <p>Paket: {{ $periodLabel }}</p>
                            @if ($active->started_at)
                                <p>Başlangıç {{ $active->started_at->format('d.m.Y') }}</p>
                            @endif
                            <p>Bitiş {{ $paidUntil?->format('d.m.Y') ?? 'süresiz' }}</p>
                        @elseif ($expired)
                            <p>Ücretli özelliklerin kullanımı sona erdi. Devam etmek için yeni bir dönem başlatabilirsiniz.</p>
                            @if ($expired->expires_at)
                                <p>Son bitiş {{ $expired->expires_at->format('d.m.Y') }}</p>
                            @endif
                        @elseif ($needsQuote)
                            <p class="font-medium text-slate-800">Teklif gerekir</p>
                            <p>{{ $monthly['message'] }}</p>
                        @else
                            <p>AidatCep Temel yönetim açık. (Ücretsiz Abonelik)</p>
                        @endif

                        @if ($renewSoon && $ownActive && ! $ownPending)
                            <p class="pt-1 font-medium text-amber-700">
                                @if ($daysLeft < 1)
                                    Paketinizin süresi bugün doluyor. Şimdi yenileyin.
                                @else
                                    Paketinizin bitmesine {{ $daysLeft }} gün kaldı. Şimdi yenileyin.
                                @endif
                                @if (! ($monthly['requires_quote'] ?? false))
                                    <span class="mt-1 block font-normal">Aylık {{ number_format($monthly['amount'], 0, ',', '.') }} ₺ · Yıllık {{ number_format($yearly['amount'], 0, ',', '.') }} ₺</span>
                                @endif
                            </p>
                        @endif

                        @if ($ownPending && $active)
                            @if ($proofSent)
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                    Ödeme bilgileriniz alınmıştır, admin onayı bekleniyor.
                                    <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="mt-1 block underline">Sipariş Detayları</a>
                                </p>
                            @else
                                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                                    {{ $pending->subscription?->order_number ?: '—' }} numaralı ücretli kullanım siparişiniz oluşturuldu. Ödemeniz bekleniyor.
                                    Ödeme dekontunuzu <a href="{{ route('subscriber.subscriptions.receipt', $pending->subscription) }}" class="underline">Sipariş Detaylarından</a> yollayabilirsiniz.
                                </p>
                            @endif
                        @endif
                    </div>

                    @if ($active)
                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <p class="text-xs font-semibold text-emerald-800">AidatCep Ücretli Kullanım avantajları açık.</p>
                            @include('partials.paid-usage-benefits')
                        </div>
                    @elseif (! $pending && ! $quoteScale && ! $needsQuote && isset($monthly['amount'], $yearly['amount']) && $monthly['amount'] !== null && $yearly['amount'] !== null)
                        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <p class="text-xs font-semibold text-emerald-800">AidatCep Ücretli Kullanım avantajlarından yararlanın.</p>
                            @include('partials.paid-usage-benefits')
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-white px-2 py-1 text-center">
                                    <p class="text-xs text-slate-500">Aylık</p>
                                    <p class="text-sm font-semibold text-slate-950">{{ number_format($monthly['amount'], 2, ',', '.') }} ₺</p>
                                    <p class="text-xs text-slate-500">{{ number_format($monthly['net_amount'], 2, ',', '.') }} ₺ + {{ number_format($monthly['tax_amount'], 2, ',', '.') }} ₺ KDV</p>
                                </div>
                                <div class="rounded-lg bg-white px-2 py-1 text-center">
                                    <p class="text-xs text-slate-500">Yıllık</p>
                                    <p class="text-sm font-semibold text-slate-950">{{ number_format($yearly['amount'], 2, ',', '.') }} ₺</p>
                                    <p class="text-xs text-slate-500">{{ number_format($yearly['net_amount'], 2, ',', '.') }} ₺ + {{ number_format($yearly['tax_amount'], 2, ',', '.') }} ₺ KDV</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($apartment->nextSetupStep())
                        <p class="mt-4 text-sm font-semibold text-amber-700">Kurulum tamamlanmadı. Apartmanı Yönet ile kaldığınız adımdan devam edebilirsiniz.</p>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                            <button class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apartmanı Yönet</button>
                        </form>
                        @if ($showDetails ?? false)
                            <a href="{{ route('subscriber.apartments.show', $apartment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Ayrıntılar</a>
                        @endif

                        @if ($showPay)

                            <a href="{{ route('subscriber.subscriptions.create', ['apartment_id' => $apartment->id]) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">{{ $payLabel }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
