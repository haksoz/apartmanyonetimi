@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-slate-950">Aidat Planı · {{ $planYear }} · İşlem Özeti</h1>
            <p class="mt-1 text-sm text-slate-500">Henüz kayıt yapılmadı. Oluşturulacak aidatları seçip özeti onaylayın.</p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            <button type="submit" form="aidat-plan-confirm" name="update_summary" value="1" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white">Özeti Güncelle</button>
            <button type="submit" form="aidat-plan-confirm" name="return_to_form" value="1" formnovalidate class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7" /></svg>
                Geri
            </button>
        </div>
    </div>
    @if ($changed)
        <p class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Plan veya hesap bilgileri değişti. Güncellenen özeti tekrar kontrol edip onaylayın.</p>
    @endif

    <form id="aidat-plan-confirm" method="POST" action="{{ route('due-plans.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="confirmation_token" value="{{ $token }}">

        @if ($proposed->is_active)
            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-2xl bg-white p-5 shadow-sm" aria-labelledby="past-title">
                    <h2 id="past-title" class="mb-2 text-sm font-semibold text-slate-800">Geçmiş Aylar</h2>
                    @if ($summary['past']['eligible'])
                        <p class="mb-4 text-sm text-slate-500">{{ $summary['past']['months'] }} ayda {{ $summary['past']['count'] }} eksik kayıt · {{ number_format($summary['past']['amount'], 2, ',', '.') }} ₺</p>
                        <input type="hidden" name="include_past_periods" value="0">
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                            <input id="include_past_periods" name="include_past_periods" type="checkbox" value="1" @checked($summary['past']['included']) aria-describedby="past-help" class="mt-0.5 rounded border-slate-300 accent-emerald-600">
                            Geçmiş aylara ait aidatları da oluştur
                        </label>
                        <p id="past-help" class="mt-2 text-xs text-slate-500">İşaretlemezseniz geçmiş aylar oluşturulmaz. Mevcut kayıtlar korunur.</p>
                    @else
                        <p class="text-sm text-slate-500">Seçilen yılın geçmiş aylarında oluşturulacak eksik aidat yok.</p>
                    @endif
                </section>

                <section class="rounded-2xl bg-white p-5 shadow-sm" aria-labelledby="current-title">
                    <h2 id="current-title" class="mb-2 text-sm font-semibold text-slate-800">Bu Ay</h2>
                    @if ($summary['current']['eligible'])
                        <p class="mb-3 text-sm text-slate-500">{{ $summary['current']['label'] }} · {{ $summary['current']['count'] }} eksik kayıt · {{ number_format($summary['current']['amount'], 2, ',', '.') }} ₺</p>
                        <p class="mb-3 text-xs text-slate-500">Oluşturma günü geldi veya geçti. Bu ay için seçiminizi yapın.</p>
                        <label class="mb-3 flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                            <input name="current_month_action" type="radio" value="create" required @checked($summary['current']['action'] === 'create') class="mt-0.5 accent-emerald-600">
                            Bu ayın eksik aidatlarını şimdi oluştur.
                        </label>
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                            <input name="current_month_action" type="radio" value="skip" required @checked($summary['current']['action'] === 'skip') class="mt-0.5 accent-emerald-600">
                            Bu ayı atla, gelecek aydan başlat.
                        </label>
                        <p class="mt-2 text-xs text-slate-500">Atlanan ay otomatik oluşturulmaz.</p>
                    @else
                        <p class="text-sm text-slate-500">Bu ay için ek işlem gerekmiyor. Dönemlerin durumunu aşağıdan inceleyebilirsiniz.</p>
                    @endif
                </section>
            </div>
        @else
            <p class="rounded-xl bg-slate-100 p-4 text-sm text-slate-600">Plan pasif kaydedilecek; aidat oluşturulmayacak.</p>
        @endif

        <section class="rounded-2xl bg-white p-5 shadow-sm" aria-label="Onaylanacak işlem">
            <p id="summary-dirty" role="status" class="mb-4 hidden rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Seçimleriniz değişti. Güncel kayıt sayısı ve tutarı görmek için Özeti Güncelle butonuna basın.</p>
            <div id="summary-totals" class="grid gap-4 sm:grid-cols-3">
                <div><p class="text-xs text-slate-500">Şimdi Oluşturulacak</p><p class="mt-1 text-xl font-bold text-slate-900">{{ $summary['count'] }} kayıt</p></div>
                <div><p class="text-xs text-slate-500">Toplam Tutar</p><p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($summary['amount'], 2, ',', '.') }} ₺</p></div>
                <div><p class="text-xs text-slate-500">Gelecek Aylar</p><p class="mt-1 inline-flex items-center gap-2 text-sm font-semibold text-slate-700">@include('financial-plans._clock') Zamanı geldikçe</p></div>
            </div>
            <p class="mt-4 text-xs text-slate-500">Yalnızca bu planın eksik aidatları oluşturulur. Mevcut borçlar değiştirilmez.</p>
        </section>

        <details open class="overflow-hidden rounded-2xl bg-white shadow-sm">
            <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-slate-700">Dönemlere Göre İşlem Dökümü</summary>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr>
                        <th class="px-4 py-3">Dönem</th><th class="px-4 py-3">Yapılacak İşlem</th><th class="px-4 py-3">Mevcut Kayıt</th><th class="px-4 py-3">Yeni Kayıt</th><th class="px-4 py-3 text-right">Yeni Tutar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary['rows'] as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['status'] }}</td>
                                <td class="px-4 py-3">{{ $row['existing'] }}</td>
                                <td class="px-4 py-3">{{ $row['count'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 2, ',', '.') }} ₺</td>
                            </tr>
                            @if ($row['count'])
                                <tr><td colspan="5" class="px-4 pb-3">
                                    <details><summary class="cursor-pointer text-xs font-semibold text-slate-600">Hesapları ve tutarları göster</summary>
                                        <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                            @foreach ($row['accounts'] as $account)
                                                <li>{{ $account['label'] }} — {{ number_format($account['amount'], 2, ',', '.') }} ₺</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </td></tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar plan ve borçlar kaydedilmez.</p>
            <button type="submit" name="return_to_form" value="1" formnovalidate class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</button>
            <button id="confirm-plan-button" type="submit" @disabled($summary['current']['eligible'] && $summary['current']['action'] === null) class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Onayla ve Kaydet</button>
        </div>
    </form>
    <script>
        document.querySelectorAll('input[name="current_month_action"], input[type="checkbox"][name="include_past_periods"]').forEach(input => {
            input.addEventListener('change', () => {
                document.getElementById('confirm-plan-button').disabled = true;
                document.getElementById('summary-dirty').classList.remove('hidden');
                document.getElementById('summary-totals').classList.add('opacity-50');
            });
        });
    </script>
@endsection
