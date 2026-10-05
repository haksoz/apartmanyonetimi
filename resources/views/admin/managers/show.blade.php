@extends('layouts.app')

@section('title', $manager->name)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="{{ route('admin.managers.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Aboneliklere dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $manager->name }}</h1>
            <p class="text-sm text-slate-500">{{ $manager->email }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('admin.impersonate.start', $manager) }}">
                @csrf
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bu Kullanıcı Olarak Giriş Yap</button>
            </form>
            <form method="POST" action="{{ route('admin.managers.destroy', $manager) }}" onsubmit="return confirm(@js($manager->name.' ve abonelik kaydı silinecek. Emin misiniz?'))">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Kullanıcıyı Sil</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <h2 class="text-lg font-semibold text-slate-900">Siparişler</h2>
    <p class="mt-1 text-sm text-slate-500">Yalnızca bu müşterinin verdiği siparişler ve yaptığı ödemeler.</p>

    @forelse ($orders as $subscription)
        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Dönem</h3>
                    <p class="text-sm text-slate-500">Sipariş no {{ $subscription->order_number ?? '—' }}</p>
                </div>
                @include('admin.managers.partials.status', ['subscription' => $subscription])
            </div>

            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="text-slate-500">Müşteri</dt><dd class="font-medium text-slate-900">{{ $manager->name }}</dd></div>
                <div><dt class="text-slate-500">Dönem</dt><dd class="font-medium text-slate-900">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</dd></div>
                <div><dt class="text-slate-500">Başlangıç</dt><dd class="font-medium text-slate-900">{{ $subscription->started_at?->format('d.m.Y') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Bitiş</dt><dd class="font-medium text-slate-900">{{ $subscription->expires_at?->format('d.m.Y') ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Toplam</dt><dd class="font-medium text-slate-900">{{ number_format($subscription->price, 2, ',', '.') }} ₺</dd></div>
                <div><dt class="text-slate-500">Ödeme yöntemi</dt><dd class="font-medium text-slate-900">{{ $subscription->payment_method === 'kredi_kartı' ? 'Kredi kartı' : ($subscription->payment_method === 'nakit' ? 'Nakit' : 'Havale / EFT') }}</dd></div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-500">Dekont / referans</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $subscription->receipt_reference ?: '—' }}
                        @if ($subscription->receipt_path)
                            <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="ml-2 text-sm font-semibold text-emerald-700">Dekontu aç</a>
                        @endif
                    </dd>
                </div>
            </dl>

            <h4 class="mt-6 text-sm font-semibold text-slate-900">Apartman hizmet kalemleri</h4>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Apartman</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Daire</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Bant</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Sınır</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Liste</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Kampanya</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($subscription->items as $item)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $item->apartment_name }}</td>
                                <td class="px-3 py-2">{{ $item->unit_count }}</td>
                                <td class="px-3 py-2">{{ $item->band_label }}</td>
                                <td class="px-3 py-2">{{ $item->band_min_units }}–{{ $item->band_max_units ?? '∞' }}</td>
                                <td class="px-3 py-2 text-right">{{ $item->list_amount !== null ? number_format($item->list_amount, 0, ',', '.').' ₺' : '—' }}</td>
                                <td class="px-3 py-2">
                                    @if ($item->campaign_name)
                                        {{ $item->campaign_name }}
                                        @if ($item->discount_amount)
                                            · −{{ number_format($item->discount_amount, 0, ',', '.') }} ₺
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-medium">{{ number_format($item->amount, 0, ',', '.') }} ₺</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="px-3 py-2 text-right font-semibold text-slate-700">Toplam</td>
                            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ number_format($subscription->items->sum('amount'), 0, ',', '.') }} ₺</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <h4 class="mt-6 text-sm font-semibold text-slate-900">Ödemeler</h4>
            @if ($subscription->payments->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Henüz tahsilat yok. Bekleyen ödeme sipariş durumundadır.</p>
            @else
                <table class="mt-3 min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Tarih</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Tutar</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Yöntem</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Referans</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Durum</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Dekont</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subscription->payments as $payment)
                            <tr>
                                <td class="px-3 py-2">{{ $payment->payment_date?->format('d.m.Y') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($payment->amount, 2, ',', '.') }} ₺</td>
                                <td class="px-3 py-2">{{ $payment->payment_method }}</td>
                                <td class="px-3 py-2">{{ $payment->reference_code ?? '—' }}</td>
                                <td class="px-3 py-2">Tahsil edildi</td>
                                <td class="px-3 py-2">
                                    @if ($subscription->receipt_path)
                                        <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="font-semibold text-emerald-700">Dekont</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($subscription->isPending())
                    @include('admin.managers.partials.approve', ['manager' => $manager, 'subscription' => $subscription])
                @elseif ($subscription->isCovering())
                    <form method="POST" action="{{ route('admin.managers.subscription.cancel', [$manager, $subscription]) }}" onsubmit="return confirm('Bu dönem sonlandırılacak. Emin misiniz?')">
                        @csrf
                        <button class="rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700">Dönemi sonlandır</button>
                    </form>
                @elseif (! $subscription->is_active && ! $subscription->isCancelled())
                    <form method="POST" action="{{ route('admin.managers.subscription.reactivate', [$manager, $subscription]) }}">
                        @csrf
                        @method('PATCH')
                        <button class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">Geri yükle</button>
                    </form>
                @endif
            </div>
        </section>
    @empty
        <p class="mt-4 text-sm text-slate-500">Bu müşterinin apartman hizmet siparişi yok.</p>
    @endforelse

    @if ($legacyOrders->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold text-slate-900">Eski paket kayıtları</h2>
        <p class="mt-1 text-sm text-slate-500">Bu kayıtlar ücretli apartman hakkı vermez.</p>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">Tutar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach ($legacyOrders as $subscription)
                        <tr>
                            <td class="px-4 py-3">Eski paket kaydı · {{ $subscription->package?->name ?? 'Paket' }}</td>
                            <td class="px-4 py-3">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($subscription->price, 2, ',', '.') }} ₺</td>
                            <td class="px-4 py-3">
                                @include('admin.managers.partials.status', ['subscription' => $subscription])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
