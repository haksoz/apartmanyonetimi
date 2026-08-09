@extends('layouts.app')

@section('content')
    @include('apartments.wizard._steps', ['activeStep' => 4])

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kategoriler</h1>
        <p class="mt-1 text-sm text-slate-500">Varsayılan kategoriler zaten oluşturuldu. İsterseniz yeni kategori ekleyebilir veya bu adımı atlayabilirsiniz.</p>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Mevcut Kategoriler</h2>
        <ul class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            @foreach ($categories as $category)
                <li class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-2 text-sm">
                    <span>{{ $category->name }}</span>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $category->type_label }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <form method="POST" action="{{ route('apartments.wizard.categories.store', $apartment) }}" class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Yeni Kategori Ekle</h2>
        <div class="grid gap-5 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kategori Adı</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="type" class="mb-2 block text-sm font-semibold text-slate-700">Kullanım Tipi</label>
                <select id="type" name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    <option value="all" @selected(old('type', 'all') === 'all')>Tümü</option>
                    <option value="income" @selected(old('type') === 'income')>Gelir / Tahsilat</option>
                    <option value="expense" @selected(old('type') === 'expense')>Gider</option>
                </select>
                @error('type')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mt-4">
            <button type="submit" class="rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700">Kategori Ekle</button>
        </div>
    </form>

    <form method="POST" action="{{ route('apartments.wizard.finish', $apartment) }}" class="flex justify-end gap-3">
        @csrf
        <button type="submit" name="action" value="skip" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Atla ve Bitir</button>
        <button type="submit" name="action" value="finish" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kurulumu Tamamla</button>
    </form>
@endsection
