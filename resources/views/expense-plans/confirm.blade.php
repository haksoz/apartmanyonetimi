@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-slate-950">Gider Planı · İşlem Özeti</h1>
            <p class="mt-1 text-sm text-slate-500">Henüz kayıt yapılmadı. Oluşturulacak giderleri kontrol edip onaylayın.</p>
        </div>
        <div class="flex shrink-0 justify-end gap-2">
            <button type="submit" form="expense-plan-confirm" name="update_summary" value="1" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Özeti Güncelle</button>
            <button type="submit" form="expense-plan-confirm" name="return_to_form" value="1" formnovalidate aria-label="Forma geri dön" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7" /></svg><span class="hidden sm:inline">Geri</span>
            </button>
        </div>
    </div>
    @if ($changed)
        <p class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Özet güncellendi. Oluşturulacak giderleri tekrar kontrol edip onaylayın.</p>
    @endif
    <form id="expense-plan-confirm" action="{{ route('expense-plans.confirm') }}" method="POST" class="space-y-5">
        @csrf
        <input type="hidden" name="confirmation_token" value="{{ $token }}">
        <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            <h2 class="break-words font-semibold text-slate-900">{{ $proposed->name }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $proposed->account?->name }} · {{ $proposed->category?->name }}</p>
            <p class="mt-2 text-sm text-slate-600">Aylık {{ number_format($proposed->amount, 2, ',', '.') }} ₺ · {{ $proposed->generation_mode === 'on_schedule' ? 'Zamanı geldikçe oluşturulur' : 'Tümünü şimdi oluştur' }}</p>
            <p class="mt-2 text-xs text-slate-500">Mevcut giderler değişmez. Yeni giderler ödenmemiş olarak kaydedilir; ödeme veya kasa hareketi oluşturulmaz.</p>
        </section>
        @if ($proposed->is_active)
            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Geçmiş Aylar</h2>
                    @if ($summary['past_count'] > 0)
                        <p class="mb-3 text-sm text-slate-500">{{ $summary['past_count'] }} ayda eksik gider kaydı var.</p>
                        <input type="hidden" name="include_past_periods" value="0">
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="include_past_periods" value="1" @checked($summary['include_past']) class="mt-0.5 rounded border-slate-300">
                            Geçmiş aylara ait giderleri de oluştur
                        </label>
                    @else
                        <p class="text-sm text-slate-500">Geçmiş aylarda oluşturulacak eksik gider yok.</p>
                    @endif
                </section>
                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Bu Ay</h2>
                    @if ($summary['current_eligible'])
                        <p class="mb-3 text-sm text-slate-500">Bu ayın oluşturma günü gelmiş, gideri henüz oluşmamış.</p>
                        @foreach (['create' => 'Bu ayın giderini oluştur', 'skip' => 'Bu ayı atla'] as $value => $label)
                            <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="radio" name="current_month_action" value="{{ $value }}" @checked($summary['current_action'] === $value) required> {{ $label }}</label>
                        @endforeach
                    @else
                        <p class="text-sm text-slate-500">{{ $proposed->generation_mode === 'create_all_now' ? 'Bu ay plan kapsamındaysa ve kaydı yoksa oluşturulur.' : 'Bu ay için ek seçim gerekmiyor. Plan, oluşturma takvimine göre çalışır.' }}</p>
                    @endif
                </section>
            </div>
        @else
            <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Plan pasif kaydedilecek. Gider oluşturulmayacak.</p>
        @endif
        <p id="expense-summary-dirty" class="hidden rounded-xl bg-amber-50 p-4 text-sm text-amber-900" role="status">Seçimler değişti. Onaylamadan önce sağ üstten özeti güncelleyin.</p>
        <section id="expense-summary-totals" class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
            <p class="text-lg font-semibold text-slate-900">{{ $summary['count'] }} yeni gider · {{ number_format($summary['amount'], 2, ',', '.') }} ₺</p>
            <p class="mt-1 text-sm text-slate-500">{{ $summary['existing'] }} mevcut kayıt korunacak.</p>
            @if (! $proposed->end_date)
                <p class="mt-2 text-xs text-slate-500">Bitiş tarihi yok. Tabloda geçmiş aylar ve önümüzdeki iki ay gösterilir; plan sonraki aylarda da zamanı geldikçe çalışır.</p>
            @endif
        </section>
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm" aria-label="Gider dönemleri">
            <div class="overflow-x-auto">
                <table class="hidden min-w-full text-left text-sm md:table">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                        <th class="px-4 py-3">Dönem / Açıklama</th><th class="px-4 py-3">Oluşturma Tarihi</th><th class="px-4 py-3">Son Ödeme Tarihi</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3 text-right">Tutar</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary['rows'] as $row)
                            <tr><td class="px-4 py-3"><p class="font-medium text-slate-900">{{ $row['label'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['description'] }}</p></td><td class="whitespace-nowrap px-4 py-3">{{ $row['date'] }}</td><td class="whitespace-nowrap px-4 py-3">{{ $row['due'] ?? '—' }}</td><td class="px-4 py-3 {{ $row['create'] ? 'font-semibold text-emerald-700' : 'text-slate-500' }}">{{ $row['status'] }}</td><td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 2, ',', '.') }} ₺</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($summary['rows'] as $row)
                    <article class="p-4 text-sm">
                        <div class="flex flex-wrap justify-between gap-2"><h3 class="font-semibold text-slate-900">{{ $row['label'] }}</h3><span class="{{ $row['create'] ? 'font-semibold text-emerald-700' : 'text-slate-500' }}">{{ $row['status'] }}</span></div>
                        <p class="mt-2 break-words text-slate-600">{{ $row['description'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">Oluşturma: {{ $row['date'] }} · Vade: {{ $row['due'] ?? '—' }}</p>
                        <p class="mt-2 font-semibold tabular-nums text-slate-900">{{ number_format($row['amount'], 2, ',', '.') }} ₺</p>
                    </article>
                @endforeach
            </div>
        </section>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar kayıt yapılmaz.</p>
            <button type="submit" name="return_to_form" value="1" formnovalidate class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</button>
            <button id="confirm-expense-plan" type="submit" @disabled($summary['current_eligible'] && $summary['current_action'] === null) class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Onayla ve Kaydet</button>
        </div>
    </form>
    <script>
        document.querySelectorAll('input[name="current_month_action"], input[type="checkbox"][name="include_past_periods"]').forEach(input => {
            input.addEventListener('change', () => {
                document.getElementById('confirm-expense-plan').disabled = true;
                document.getElementById('expense-summary-dirty').classList.remove('hidden');
                document.getElementById('expense-summary-totals').classList.add('opacity-50');
            });
        });
    </script>
@endsection
