@extends('layouts.app')

@section('wizard_steps')
    @include('apartments.wizard._steps', ['activeStep' => 4])
@endsection

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kategoriler</h1>
        <p class="mt-1 text-sm text-slate-500">Varsayılan kategoriler zaten oluşturuldu. İsterseniz yeni kategori ekleyebilir veya bu adımı atlayabilirsiniz.</p>
    </div>

    <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Mevcut Kategoriler</h2>
        <ul class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
            @foreach ($categories as $category)
                <li class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-2 text-sm">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-800">{{ $category->name }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">{{ implode(', ', $category->usage_labels) ?: 'Kullanım yetkisi yok' }}</div>
                    </div>
                    @if ((int) $apartment->default_aidat_category_id === (int) $category->id)
                        <span class="ml-3 shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Genel Aidat</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <form method="POST" action="{{ route('apartments.wizard.categories.store', $apartment) }}" class="mb-6 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Yeni Kategori Ekle</h2>
        <div class="space-y-5">
            <div>
                <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Kategori Adı</label>
                <input id="name" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold text-slate-700">Kullanım Yetkileri</legend>
                <p class="mb-3 text-xs text-slate-500">Kategorinin kullanılabileceği borç ve gider işlemlerini seçin.</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (\App\Models\Category::USAGE_PERMISSIONS as $permission => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-sm text-slate-700">
                            <input type="checkbox" name="{{ $permission }}" value="1" @checked(old($permission, false)) class="rounded border-slate-300">
                            {{ $label }}
                        </label>
                        @error($permission)<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                    @endforeach
                </div>
            </fieldset>
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
