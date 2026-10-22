@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0"><h1 class="text-2xl font-bold text-slate-950">Taksitli Borç Planı · İşlem Özeti</h1><p class="mt-1 text-sm text-slate-500">Henüz kayıt yapılmadı. Taksitleri ve oluşturulacak borçları kontrol edip onaylayın.</p></div>
        <div class="flex shrink-0 justify-end gap-2">
            <button type="submit" form="assessment-confirm" name="update_summary" value="1" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Özeti Güncelle</button>
            <button type="submit" form="assessment-confirm" name="return_to_form" value="1" formnovalidate aria-label="Forma geri dön" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7" /></svg><span class="hidden sm:inline">Geri</span></button>
        </div>
    </div>
    @if ($changed)
        <p class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Özet güncellendi. Daire paylarını ve oluşturulacak borçları tekrar kontrol edip onaylayın.</p>
    @endif
    <form id="assessment-confirm" method="POST" action="{{ route('assessment-plans.confirm') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="confirmation_token" value="{{ $token }}">
        <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-6">
            <h2 class="break-words font-semibold text-slate-900">{{ $proposed->name }}</h2>
            <p class="mt-2 text-sm text-slate-600">Toplam {{ number_format($proposed->total_amount, 2, ',', '.') }} ₺ · {{ $proposed->installment_count }} taksit · {{ match ($proposed->distribution_type) { 'square_meters' => 'Metrekareye göre', 'share_coefficient' => 'Arsa payına göre', default => 'Eşit dağıtım' } }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ $proposed->target_audience === 'owner_only' ? 'Yalnızca malikler borçlandırılır.' : 'İlgili dönemde kiracı varsa kiracıya, yoksa malike borçlandırılır.' }}</p>
            <p class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">@if ($proposed->createsOnSchedule()) @include('financial-plans._clock') @endif {{ $proposed->createsOnSchedule() ? 'Zamanı geldikçe oluşturulur.' : 'Tümünü şimdi oluştur.' }}</p>
            <p class="mt-2 text-sm text-slate-600">Borç türü: {{ $proposed->due_type->label() }} · İşin kategorisi: {{ $summary['category']['name'] }}</p>
            @if ($summary['category']['is_new'])
                <p class="mt-2 text-xs text-slate-500">Onayınızla “{{ $summary['category']['name'] }}” kategorisi Tümü tipinde oluşturulacak; hem borçlarda hem ilgili giderlerde kullanılabilecek.</p>
            @else
                <p class="mt-2 text-xs text-slate-500">Mevcut kategori korunacak; adı ve tipi değiştirilmeyecek.</p>
            @endif
            <p class="mt-2 text-xs text-slate-500">Mevcut taksitler, daire payları ve borçlar değişmez. Borçlanacak hesap, kayıt oluşturulurken ilgili döneme göre belirlenir.</p>
        </section>
        @if ($proposed->is_active)
            <div class="grid gap-4 md:grid-cols-2">
                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Geçmiş Aylar</h2>
                    @if ($summary['past_count'] > 0)
                        <p class="mb-3 text-sm text-slate-500">Geçmiş aylarda {{ $summary['past_count'] }} taksit oluşturulmayı bekliyor.</p>
                        <input type="hidden" name="include_past_installments" value="0">
                        <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="include_past_installments" value="1" @checked($summary['include_past']) class="mt-0.5 rounded border-slate-300">Geçmiş aylara ait taksitleri de oluştur</label>
                        <p class="mt-2 text-xs text-slate-500">İşaretlemezseniz geçmiş aylar oluşturulmaz. Mevcut borçlar korunur.</p>
                    @else
                        <p class="text-sm text-slate-500">Geçmiş aylarda oluşturulacak taksit yok.</p>
                    @endif
                </section>
                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="mb-2 text-sm font-semibold text-slate-800">Bu Ay</h2>
                    @if ($summary['current_eligible'])
                        <p class="mb-3 text-sm text-slate-500">Bu ayın oluşturma günü geldi veya geçti. Bekleyen taksitler için seçiminizi yapın.</p>
                        @foreach (['create' => 'Bu ayın taksitlerini oluştur', 'skip' => 'Bu ayı atla'] as $value => $label)
                            <label class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input type="radio" name="current_month_action" value="{{ $value }}" @checked($summary['current_action'] === $value) required> {{ $label }}</label>
                        @endforeach
                        <p class="mt-2 text-xs text-slate-500">Atlanan ay otomatik oluşturulmaz.</p>
                    @else
                        <p class="text-sm text-slate-500">{{ $proposed->createsOnSchedule() ? 'Bu ay için ek seçim gerekmiyor. Taksitler oluşturma takvimine göre çalışır.' : 'Bu ayın bekleyen taksitleri son onayınızla oluşturulur.' }}</p>
                    @endif
                </section>
            </div>
        @else
            <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Plan pasif kaydedilecek. Borç oluşturulmayacak.</p>
        @endif
        <p id="assessment-dirty" role="status" class="hidden rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Seçimler değişti. Onaylamadan önce sağ üstten özeti güncelleyin.</p>
        <section id="assessment-totals" class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
            <p class="text-lg font-semibold text-slate-900">{{ $summary['count'] }} yeni borç · {{ number_format($summary['amount'], 2, ',', '.') }} ₺</p>
            <p class="mt-1 text-sm text-slate-500">{{ $summary['existing'] }} mevcut borç kaydı korunacak.</p>
        </section>
        <section class="overflow-hidden rounded-2xl bg-white shadow-sm" aria-label="Taksitlere göre işlem dökümü">
            <h2 class="px-4 py-4 text-sm font-semibold text-slate-700">Taksitlere Göre İşlem Dökümü</h2>
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">Taksit / Oluşturma Tarihi</th><th class="px-4 py-3">İşlem</th><th class="px-4 py-3">Mevcut / Yeni Borç</th><th class="px-4 py-3 text-right">Yeni Tutar</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary['rows'] as $row)
                            <tr><td class="px-4 py-3"><p class="font-medium">{{ $row['sequence'] }}. Taksit · {{ $row['creation_date'] }}</p><p class="mt-1 break-words text-xs text-slate-600">{{ $row['description'] }}</p></td><td class="px-4 py-3">{{ $row['status'] }}</td><td class="px-4 py-3 tabular-nums">{{ $row['existing'] }} / {{ $row['count'] }}</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($row['amount'], 2, ',', '.') }} ₺</td></tr>
                            <tr><td colspan="4" class="px-4 pb-3">@include('assessment-plans._summary-accounts')</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($summary['rows'] as $row)
                    <article class="min-w-0 p-4 text-sm">
                        <div class="flex flex-wrap justify-between gap-2"><h3 class="font-semibold text-slate-900">{{ $row['sequence'] }}. Taksit · {{ $row['creation_date'] }}</h3><span class="text-slate-600">{{ $row['status'] }}</span></div>
                        <p class="mt-2 break-words text-slate-600">{{ $row['description'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">Mevcut: {{ $row['existing'] }} · Yeni: {{ $row['count'] }} borç</p>
                        <p class="mt-2 font-semibold tabular-nums text-slate-900">Yeni tutar: {{ number_format($row['amount'], 2, ',', '.') }} ₺</p>
                        <div class="mt-3">@include('assessment-plans._summary-accounts')</div>
                    </article>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-700">Daire Payları · {{ count($summary['shares']) }} daire</h2>
            @if (count($summary['shares']) > 0)
                <button type="button" data-period-accounts="shares" aria-haspopup="dialog" aria-controls="period-accounts-dialog" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">Daire paylarını göster</button>
            @endif
        </section>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <p class="mr-auto text-xs text-slate-500">Son onayınıza kadar plan, taksit ve borç kaydı yapılmaz.</p>
            <button type="submit" name="return_to_form" value="1" formnovalidate class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">İptal</button>
            <button id="confirm-assessment" type="submit" @disabled($summary['current_eligible'] && $summary['current_action'] === null) class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Onayla ve Kaydet</button>
        </div>
    </form>
    @include('assessment-plans._summary-accounts-dialog')
    <script>
        document.querySelectorAll('input[name="current_month_action"], input[type="checkbox"][name="include_past_installments"]').forEach(input => {
            input.addEventListener('change', () => {
                document.getElementById('confirm-assessment').disabled = true;
                document.getElementById('assessment-dirty').classList.remove('hidden');
                document.getElementById('assessment-totals').classList.add('opacity-50');
            });
        });
    </script>
@endsection
