@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Aidat Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">Hesap, daire, kategori, tutar, dönem ve tarih bilgilerini güncelleyin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('dues.show', $due) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Detaya Dön</a>
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
            Hesap, daire ve tutar değiştirilemez. Yalnızca kategori, dönem, tarih ve açıklama düzenlenebilir.
            <a href="{{ route('dues.show', $due) }}" class="ml-1 underline font-semibold">Ödeme tahsislerini görüntüle →</a>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('dues.update', $due) }}" class="space-y-4">
        @csrf
        @method('PATCH')

        {{-- Hesap & Borç Bilgisi --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Hesap &amp; Borç Bilgisi</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="account_id" class="mb-2 block text-sm font-medium text-slate-600">Borçlandırılacak Hesap</label>
                    @if ($isLocked)
                        <input type="hidden" name="account_id" value="{{ $due->account_id }}">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">{{ $due->account?->name ?? 'Hesap seçilmedi' }}</div>
                    @else
                        <select id="account_id" name="account_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            <option value="">Hesap seçin</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('account_id', $due->account_id) === (string) $account->id)>
                                    {{ $account->unit ? 'No:'.str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' ' : '' }}{{ $account->name }} ({{ $account->type_label }}){{ !$account->is_active ? ' - Pasif' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('account_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div>
                    <label for="unit_id" class="mb-2 block text-sm font-medium text-slate-600">Daire</label>
                    @if ($isLocked)
                        <input type="hidden" name="unit_id" value="{{ $due->unit_id }}">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-500 cursor-not-allowed">{{ $due->unit ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT).' No.lu Daire' : 'Daire seçilmedi' }}</div>
                    @else
                        <select id="unit_id" name="unit_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            <option value="">Daire seçin</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" @selected((string) old('unit_id', $due->unit_id) === (string) $unit->id)>{{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire</option>
                            @endforeach
                        </select>
                        @error('unit_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div>
                    <label for="category_id" class="mb-2 block text-sm font-medium text-slate-600">Borç Kategorisi</label>
                    <select id="category_id" name="category_id" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                        <option value="">Kategori seçin</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $due->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
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

                <div>
                    <label for="period" class="mb-2 block text-sm font-medium text-slate-600">Borç Dönemi</label>
                    <input id="period" name="period" type="month" value="{{ old('period', $due->period) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('period')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div class="md:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-600">Açıklama</label>
                    <input id="description" name="description" value="{{ old('description', $due->description) }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none" placeholder="Örn. Hasar tazminatı, ceza vb.">
                    @error('description')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Tarih Bilgileri --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Tarih Bilgileri</h3>
            <div class="grid gap-5 md:grid-cols-2">
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
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full md:w-auto rounded-xl bg-slate-950 px-8 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </div>
    </form>
@endsection
