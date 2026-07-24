@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-row items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Aidat bilgilerini güncelleyin.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="history.back()" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 inline-flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </button>
        </div>
    </div>

    @php $isLocked = $due->status === 'paid' || $due->allocations->isNotEmpty(); @endphp

    @if ($isLocked)
    <div class="mb-4 rounded-2xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
        <svg class="h-5 w-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="text-sm text-amber-800">
            <span class="font-semibold">
                @if ($due->status === 'paid') Bu aidat ödenmiştir. @else Bu aidada ödeme tahsisi yapılmıştır. @endif
            </span>
            Hesap, tutar ve dönem değiştirilemez. Yalnızca borç türü, kategori, tarih ve açıklama düzenlenebilir.
            <a href="{{ route('dues.show', $due) }}" class="ml-1 underline font-semibold">Ödeme tahsislerini görüntüle →</a>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('dues.update', $due) }}" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" id="period" name="period" value="{{ old('period', $due->period) }}">

        {{-- Main Fields --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="account_id" class="mb-2 block text-sm font-medium text-slate-600">Hesap / Kiracı-Katmaliki</label>
                    <input type="hidden" name="account_id" value="{{ $due->account_id }}">
                    <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">
                        {{ $due->account?->name ?? 'Hesap seçilmedi' }}
                        @if ($due->account?->unit)
                            (No:{{ str_pad($due->account->unit->unit_no, 2, '0', STR_PAD_LEFT) }})
                        @endif
                        {{ $due->account ? '('.$due->account->type_label.')' : '' }}
                    </div>
                </div>

                <div>
                    <label for="due_type" class="mb-2 block text-sm font-medium text-slate-600">Borç Türü <span class="text-red-500">*</span></label>
                    <select id="due_type" name="due_type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Tür seçin</option>
                        @foreach ($dueTypes as $t)
                            <option value="{{ $t['value'] }}" @selected(old('due_type', $due->due_type?->value) === $t['value'])>{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                    @error('due_type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Kategori <span class="text-xs text-slate-400">(isteğe bağlı)</span></label>
                    <select id="category_id" name="category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) old('category_id', $due->category_id) === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="created_at_manual" class="mb-2 block text-sm font-medium text-slate-600">Oluşturulma Tarihi</label>
                    <input id="created_at_manual" name="created_at_manual" type="date" value="{{ old('created_at_manual', ($due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->toDateString() : $due->created_at->toDateString())) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('created_at_manual')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="due_date" class="mb-2 block text-sm font-medium text-slate-600">Son Ödeme Tarihi</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $due->due_date?->toDateString()) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('due_date')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="amount" class="mb-2 block text-sm font-medium text-slate-600">Borç Tutarı</label>
                    @if ($isLocked)
                        <input type="hidden" name="amount" value="{{ $due->amount }}">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                    @else
                        <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $due->amount) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        @error('amount')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description', $due->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Hasar tazminatı, ceza vb.">
                    @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </div>
    </form>

    <script>
        (() => {
            const periodInput = document.getElementById('period');
            const createdAtInput = document.getElementById('created_at_manual');

            const syncPeriodFromCreatedAt = () => {
                const dateVal = createdAtInput?.value;
                if (dateVal) {
                    periodInput.value = dateVal.substring(0, 7);
                }
            };

            createdAtInput?.addEventListener('change', syncPeriodFromCreatedAt);

            // Init
            syncPeriodFromCreatedAt();
        })();
    </script>
@endsection
