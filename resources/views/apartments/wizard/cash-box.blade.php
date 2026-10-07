@extends('layouts.app')

@section('wizard_steps')
    <div class="-mt-4">
        @include('apartments.wizard._steps', ['activeStep' => 2])
    </div>
@endsection

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Kasa Oluşturun</h1>
        <p class="mt-1 text-sm text-slate-500">Apartmanınız için bir kasa tanımlayın. Bu adım zorunludur.</p>
    </div>

    <form method="POST" action="{{ route('apartments.wizard.cash-box.store', $apartment) }}" class="max-w-2xl rounded-2xl bg-white p-4 shadow-sm sm:p-5">
        @csrf
        <div class="space-y-3">
            <div>
                <label for="name" class="text-sm font-medium text-slate-700">Kasa Adı</label>
                <input id="name" name="name" value="{{ old('name', 'Nakit Kasa') }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none" placeholder="Nakit Kasa">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="description" class="text-sm font-medium text-slate-700">Açıklama</label>
                <input id="description" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none" placeholder="Kasanın kullanım amacı">
                @error('description')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="bank_name" class="text-sm font-medium text-slate-700">Banka Adı</label>
                    <input id="bank_name" name="bank_name" value="{{ old('bank_name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                    @error('bank_name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="account_number" class="text-sm font-medium text-slate-700">Hesap No</label>
                    <input id="account_number" name="account_number" value="{{ old('account_number') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                    @error('account_number')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>

            <div>
                <label for="iban" class="text-sm font-medium text-slate-700">IBAN</label>
                <input id="iban" name="iban" value="{{ old('iban') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                @error('iban')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-4 flex items-center justify-end gap-3">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet ve Devam Et</button>
        </div>
    </form>
@endsection
