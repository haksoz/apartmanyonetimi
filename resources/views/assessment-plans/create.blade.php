@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Ek Borçlandırma Planı</h1>
        <p class="mt-1 text-sm text-slate-500">Toplam tutar dairelere dağıtılır ve taksitlere bölünür. Dağıtım kayıttan sonra değişmez.</p>
    </div>

    <form method="POST" action="{{ route('assessment-plans.store') }}" class="max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Plan / proje adı</label>
            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Açıklama</label>
            <input name="description" value="{{ old('description') }}" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Toplam tutar</label>
                <input type="number" step="0.01" min="0.01" name="total_amount" value="{{ old('total_amount') }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Kategori</label>
                <select name="category_id" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                    <option value="">Seçiniz (opsiyonel)</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Dağıtım yöntemi</label>
            <select name="distribution_type" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
                <option value="equal" @selected(old('distribution_type', 'equal') === 'equal')>Eşit dağıtım</option>
                <option value="square_meters" @selected(old('distribution_type') === 'square_meters')>Metrekareye göre</option>
                <option value="share_coefficient" @selected(old('distribution_type') === 'share_coefficient')>Arsa payına göre</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Hedef kitle</label>
            <div class="flex gap-3">
                <label class="flex-1 cursor-pointer rounded-lg border border-slate-200 p-2.5 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="target_audience" value="owner_only" class="sr-only peer" @checked(old('target_audience', 'owner_only') === 'owner_only')>
                    <div class="text-sm font-semibold">Sadece Sahipler</div>
                </label>
                <label class="flex-1 cursor-pointer rounded-lg border border-slate-200 p-2.5 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="target_audience" value="tenant_priority" class="sr-only peer" @checked(old('target_audience') === 'tenant_priority')>
                    <div class="text-sm font-semibold">Kiracı Öncelikli</div>
                </label>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Taksit sayısı</label>
                <input type="number" min="1" max="60" name="installment_count" value="{{ old('installment_count', 5) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">İlk vade</label>
                <input type="date" name="first_due_date" value="{{ old('first_due_date', now()->addMonth()->startOfMonth()->format('Y-m-d')) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-600">Vade aralığı (ay)</label>
                <input type="number" min="1" max="12" name="due_interval_months" value="{{ old('due_interval_months', 1) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm">
            </div>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-600">Borç oluşturma yöntemi</label>
            <div class="flex flex-col gap-2">
                <label class="rounded-lg border border-slate-200 p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="generation_mode" value="create_all_now" @checked(old('generation_mode', 'create_all_now') === 'create_all_now')>
                    <span class="ml-2 text-sm font-semibold">Tümünü şimdi oluştur</span>
                    <span class="mt-1 block text-xs text-slate-500">Varsayılan. Geçmiş vadeler için onay istenir.</span>
                </label>
                <label class="rounded-lg border border-slate-200 p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="generation_mode" value="on_schedule" @checked(old('generation_mode') === 'on_schedule')>
                    <span class="ml-2 text-sm font-semibold">Zamanı geldikçe oluştur</span>
                </label>
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-600">Oluşturma günü</label>
            <input type="number" min="1" max="28" name="generate_day" value="{{ old('generate_day', 1) }}" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm md:w-1/3">
            <p class="mt-1 text-xs text-slate-500">Vadeden bağımsızdır; zamanı geldikçe oluşturmada kullanılır.</p>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">İptal</a>
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Kaydet</button>
        </div>
    </form>
@endsection
