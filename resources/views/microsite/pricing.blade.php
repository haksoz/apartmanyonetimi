@extends('layouts.landing')

@section('title', 'Fiyatlandırma — AidatCep')

@section('content')

@php
    $formatTariff = function ($amount) {
        return number_format((float) $amount, 2, ',', '.').' ₺';
    };
    $publicRows = [];
    foreach ($priceBands as $band) {
        $suffix = $band->per_unit ? ' / daire' : ' / apartman';
        $publicRows[] = [
            'label' => $band->label,
            'monthly' => $band->monthly_price === null ? 'Fiyat henüz tanımlanmadı' : $formatTariff($band->monthly_price).$suffix,
            'yearly' => $band->yearly_price === null ? 'Fiyat henüz tanımlanmadı' : $formatTariff($band->yearly_price).$suffix,
        ];
    }
@endphp

@include('microsite.partials.header')

<main class="pt-32 pb-20 px-4 sm:px-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-950 mb-3">Fiyatlandırma</h1>
        <p class="text-slate-500 text-lg mb-3">Ücretli abonelik bedeli, apartmanınızdaki daire sayısına ve seçilen abonelik dönemine göre belirlenir.</p>
        <p class="mb-10 text-sm font-medium text-slate-600">Fiyatlara KDV dahil değildir.</p>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 sm:p-8 mb-8">
            <h2 class="text-xl font-bold text-slate-950">Temel Kullanım — Ücretsiz</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-700">
                <li>Her müşteri için 1 apartman yönetimi</li>
                <li>Daire sayısı sınırı olmadan kullanım</li>
                <li>Süresiz ve abonelik ücreti olmadan temel kullanım</li>
                <li>Temel aidat, tahsilat, gider ve yönetim özellikleri</li>
            </ul>
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
            <li>101 ve üzeri daireli apartmanlarda birim fiyat toplam daire sayısına uygulanır. Özel teklif alınması gerekmez.</li>
            <li>Ücretli abonelik aylık veya yıllık olarak seçilebilir.</li>
            <li>Her apartmanın ücretli abonelik ve sipariş işlemleri ayrı yürütülür.</li>
            <li>Yenileme, yeni bir sipariş oluşturularak yapılır.</li>
            <li>Birden fazla apartman yönetmek isteyen müşterilerimiz <a href="mailto:info@aidatcep.com" class="font-semibold text-emerald-800 underline">info@aidatcep.com</a> adresinden bizimle iletişime geçebilir.</li>
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
