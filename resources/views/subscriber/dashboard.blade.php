@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Abone Paneli</h1>
        <p class="text-sm text-slate-500">Abonelik, ödemeler ve apartman yönetimi.</p>
    </div>

    @if ($apartments->count() === 0)
        <div id="no-apartment-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-8 w-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">Apartman Ekleyin</h3>
                    <p class="mt-2 text-sm text-slate-600">Sistemi kullanmaya başlamak için lütfen bir apartman oluşturun.</p>
                    <div class="mt-6 flex justify-center gap-3">
                        <a href="{{ route('subscriber.apartments.create') }}" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Apartman Oluştur</a>
                        <button onclick="document.getElementById('no-apartment-modal').remove()" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Daha Sonra</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isTrial && $subscription && $subscription->expires_at)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800">
                        Deneme süreciniz {{ $subscription->expires_at->format('d.m.Y') }} tarihine kadardır.
                    </p>
                    @if ($fallbackPackage)
                        <p class="text-sm text-amber-700 mt-1">
                            Bu tarihten sonra paketiniz <strong>{{ $fallbackPackage->name }}</strong> olacaktır.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

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

            <p class="mt-3 text-sm text-slate-500">Yönetmek istediğiniz apartmanı seçiniz.</p>

            <div class="mt-4 space-y-2">
                @forelse ($apartments->take(5) as $apartment)
                    <form method="POST" action="{{ route('subscriber.apartment.update') }}">
                        @csrf
                        <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                        <button type="submit" class="w-full flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-base font-semibold text-slate-900 text-left hover:bg-slate-50 hover:border-slate-300 transition-colors cursor-pointer">
                            <span>{{ $apartment->name }}</span>
                            @if ($currentApartmentModel && $currentApartmentModel->id === $apartment->id)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Seçili</span>
                            @endif
                        </button>
                    </form>
                @empty
                    <p class="text-sm text-slate-500">Henüz apartman yok.</p>
                @endforelse
            </div>
        </div>
    </div>

@endsection
