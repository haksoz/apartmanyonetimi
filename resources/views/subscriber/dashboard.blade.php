@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Abone Paneli</h1>
        <p class="text-sm text-slate-500">Abonelik, ödemeler ve apartman yönetimi.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Abonelik Kartı --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-emerald-50 p-3">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 6v.75c0 1.036-.84 1.875-1.875 1.875H9.375c-1.036 0-1.875-.84-1.875-1.875V6m7.5 0v12.75c0 1.036-.84 1.875-1.875 1.875H9.375c-1.036 0-1.875-.84-1.875-1.875V6m7.5 0H15"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-slate-500">Aktif Abonelik</div>
                    @if ($subscription && ! $subscription->isExpired())
                        <div class="text-lg font-semibold text-slate-900">{{ $subscription->package?->name ?? 'Paket' }}</div>
                        <div class="text-sm text-slate-600">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</div>
                    @else
                        <div class="text-lg font-semibold text-slate-900">Aktif abonelik yok</div>
                    @endif
                </div>
            </div>

            @if ($subscription && ! $subscription->isExpired())
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Başlangıç</span>
                        <span class="font-medium text-slate-900">{{ $subscription->started_at->format('d.m.Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Bitiş</span>
                        <span class="font-medium text-slate-900">{{ $subscription->expires_at?->format('d.m.Y') ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Tutar</span>
                        <span class="font-medium text-slate-900">{{ number_format($subscription->price, 2) }} ₺</span>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('landing') }}#pricing" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Abonelik Yenile / Yükselt</a>
                </div>
            @endif
        </div>

        {{-- Yaklaşan Ödeme --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-amber-50 p-3">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-slate-500">Yaklaşan Ödeme</div>
                    @if ($upcomingPayment)
                        <div class="text-lg font-semibold text-slate-900">{{ number_format($upcomingPayment->price, 2) }} ₺</div>
                        <div class="text-sm text-slate-600">{{ $upcomingPayment->expires_at?->format('d.m.Y') ?? '-' }} tarihinde</div>
                    @else
                        <div class="text-lg font-semibold text-slate-900">Yaklaşan ödeme yok</div>
                    @endif
                </div>
            </div>

            @if ($upcomingPayment)
                <div class="mt-4">
                    <a href="{{ route('landing') }}#pricing" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Ödeme yap →</a>
                </div>
            @endif
        </div>

        {{-- Apartmanlar --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-slate-500">Apartmanlar</div>
                        <div class="text-lg font-semibold text-slate-900">{{ $apartments->count() }}</div>
                    </div>
                </div>
                <a href="{{ route('apartments.create') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Yeni</a>
            </div>

            <div class="mt-4 space-y-2">
                @forelse ($apartments->take(5) as $apartment)
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm">
                        <span class="text-slate-700">{{ $apartment->name }}</span>
                        @if ($currentApartmentModel && $currentApartmentModel->id === $apartment->id)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Seçili</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Henüz apartman yok.</p>
                @endforelse
            </div>

            <div class="mt-4">
                <a href="{{ route('subscriber.apartments.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Apartmanları yönet →</a>
            </div>
        </div>
    </div>

    {{-- Geçmiş Ödemeler --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Son Ödemeler</h2>

        @if ($recentPayments->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz ödeme kaydı bulunmuyor.</p>
        @else
            <div class="mt-4 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarih</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Açıklama</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($recentPayments as $payment)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $payment->apartment?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $payment->description ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($payment->amount, 2) }} ₺</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
