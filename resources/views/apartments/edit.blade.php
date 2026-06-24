@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Apartman Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
    </div>

    <form method="POST" action="{{ request()->routeIs('subscriber.*') ? route('subscriber.apartments.update', $apartment) : route('apartments.update', $apartment) }}" class="max-w-2xl space-y-6">
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
                <textarea name="address" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300" rows="3" required>{{ old('address', $apartment->address) }}</textarea>
                @error('address') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ request()->routeIs('subscriber.*') ? route('subscriber.apartments.index') : route('apartments.show', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</a>
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </div>
    </form>
@endsection
