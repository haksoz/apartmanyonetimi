@extends('layouts.landing')

@section('title', 'Fiyatlandırma — AidatCep')

@section('content')

@php
    $formatTariff = function ($amount) {
        return number_format((float) $amount, 2, ',', '.').' ₺';
    };
    $publicRows = [];
    $quoteAdded = false;
    foreach ($priceBands as $band) {
        if ((int) $band->min_units > 100 || $band->is_quote) {
            if (! $quoteAdded) {
                $publicRows[] = ['label' => '101+ daire', 'monthly' => 'Özel Teklif', 'yearly' => 'Özel Teklif'];
                $quoteAdded = true;
            }
            continue;
        }
        $label = ($band->max_units !== null && (int) $band->max_units > 100)
            ? $band->min_units.'–100 daire'
            : $band->label;
        $publicRows[] = [
            'label' => $label,
            'monthly' => $band->monthly_price === null ? 'Özel Teklif' : $formatTariff($band->monthly_price),
            'yearly' => $band->yearly_price === null ? 'Özel Teklif' : $formatTariff($band->yearly_price),
        ];
    }
@endphp

@include('microsite.partials.header')

<main class="pt-32 pb-20 px-4 sm:px-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">Fiyatlandırma</h1>
        <p class="text-slate-500 text-lg mb-10">Ücretli abonelik bedeli, apartmanınızdaki daire sayısına göre belirlenir.</p>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8 mb-8">
            <h2 class="text-xl font-bold text-slate-950">Temel Kullanım — Ücretsiz</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                <li>1–100 daire</li>
                <li>Süresiz temel kullanım</li>
                <li>Sipariş veya abonelik ücreti olmadan başlangıç</li>
                <li>Temel aidat, tahsilat, gider ve yönetim özellikleri</li>
            </ul>
            <p class="mt-4 text-sm text-slate-600">101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanır. Tutar sitede gösterilmez.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Daire sayısı</th>
                            <th class="px-5 py-3 font-semibold">Aylık</th>
                            <th class="px-5 py-3 font-semibold">Yıllık</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($publicRows as $row)
                            <tr>
                                <td class="px-5 py-4 font-medium text-slate-900">{{ $row['label'] }}</td>
                                <td class="px-5 py-4 text-slate-800 tabular-nums">{{ $row['monthly'] }}</td>
                                <td class="px-5 py-4 text-slate-800 tabular-nums">{{ $row['yearly'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-6 text-slate-500">Fiyat listesi şu anda görüntülenemiyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <ul class="mt-8 list-disc space-y-2 pl-5 text-sm text-slate-600">
            <li>Ücret, apartmanın daire sayısına göre belirlenir.</li>
            <li>Ücretli abonelik aylık veya yıllık olarak seçilebilir.</li>
            <li>Her apartman ayrı abonelik ve ayrı sipariş üzerinden ücretlendirilir.</li>
            <li>Yenileme, yeni bir sipariş oluşturularak yapılır.</li>
        </ul>

        <h2 class="mt-14 text-2xl font-extrabold text-slate-950 mb-4">Ödeme</h2>
        <p class="text-slate-600 leading-relaxed">Ücretli abonelik ödemesi havale/EFT ile yapılır. Banka bilgileri, siparişinizi oluşturduktan sonra hesabınızda görünür.</p>
        <ol class="mt-6 space-y-3 text-sm text-slate-700">
            <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">Ücretli abonelik siparişinizi oluşturun.</li>
            <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">Ödeme bilgilerini görüntüleyin.</li>
            <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">Ödemenizi yapın.</li>
            <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">Dekont veya ödeme referansınızı gönderin.</li>
            <li class="rounded-xl border border-slate-200 bg-white px-4 py-3">Ödeme admin tarafından onaylansın.</li>
        </ol>
    </div>
</main>

@include('microsite.partials.footer')

@endsection
