@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Apartman Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
    </div>

    <form method="POST" action="{{ route('apartments.update', $apartment) }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl bg-white p-6 shadow-sm space-y-5">
            <h2 class="text-sm font-semibold text-slate-700 border-b border-slate-100 pb-3">Genel Bilgiler</h2>

            <div>
                <label class="text-sm font-medium text-slate-700">Apartman Adı</label>
                <input name="name" value="{{ old('name', $apartment->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300" required>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Adres</label>
                <textarea name="address" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300" rows="3">{{ old('address', $apartment->address) }}</textarea>
            </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-700">Yönetici Konumu</h2>
                <p class="text-xs text-slate-400 mt-1">Bu apartmanı hangi sıfatla yönetiyorsunuz?</p>
            </div>

            <div class="space-y-3">
                {{-- Dışardan yönetiyor --}}
                <label class="cursor-pointer flex items-start gap-3 rounded-xl border-2 p-4 transition-all {{ old('manager_account_id', $apartment->manager_unit_id ? '' : 'selected') === '' && !old('manager_account_id') && !$apartment->manager_unit_id ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50' }}">
                    <input type="radio" name="manager_account_id" value="" class="mt-0.5 accent-emerald-600"
                        {{ old('manager_account_id') === null && !$apartment->manager_unit_id ? 'checked' : '' }}>
                    <div>
                        <div class="text-sm font-semibold text-slate-800">Dışardan Yönetiyorum</div>
                        <div class="text-xs text-slate-500">Bu apartmanda oturmuyorum, dışarıdan yönetiyorum.</div>
                    </div>
                </label>

                {{-- Hesap seçimi --}}
                @foreach ($accounts as $account)
                    @php
                        $isSelected = $apartment->managerUnit
                            ? $apartment->managerUnit->accounts->contains('id', $account->id)
                            : false;
                        $unitLabel = $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' No.lu Daire' : '';
                    @endphp
                    <label class="cursor-pointer flex items-start gap-3 rounded-xl border-2 p-4 transition-all {{ (old('manager_account_id') == $account->id || (!old('manager_account_id') && $isSelected)) ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50' }}">
                        <input type="radio" name="manager_account_id" value="{{ $account->id }}" class="mt-0.5 accent-emerald-600"
                            {{ (old('manager_account_id') == $account->id || (!old('manager_account_id') && $isSelected)) ? 'checked' : '' }}>
                        <div>
                            <div class="text-sm font-semibold text-slate-800">{{ $account->name }}</div>
                            <div class="text-xs text-slate-500">{{ $unitLabel }} · {{ $account->type_label }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('manager_account_id') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('apartments.show', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</a>
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </div>
    </form>
@endsection
