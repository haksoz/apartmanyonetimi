@extends('layouts.onboarding')

@section('content')
    <div class="mb-8 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-100 mb-4">
            <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-950">Hoş Geldiniz, {{ auth()->user()->name }}!</h1>
        <p class="mt-2 text-sm text-slate-500">Yönetmeye başlamak için apartmanınızı tanımlayın.</p>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}" class="rounded-2xl bg-white p-6 shadow-sm space-y-5">
        @csrf

        <div>
            <label class="text-sm font-medium text-slate-700">Apartman Adı <span class="text-red-500">*</span></label>
            <input name="name" value="{{ old('name') }}" placeholder="Örn: Gül Apartmanı"
                class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300" required>
            @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">Adres</label>
            <textarea name="address" rows="2" placeholder="Mahalle, sokak, no..."
                class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">{{ old('address') }}</textarea>
        </div>

        <div>
            <label class="text-sm font-medium text-slate-700">Toplam Daire Sayısı <span class="text-red-500">*</span></label>
            <input type="number" name="unit_count" value="{{ old('unit_count', 12) }}" min="1" max="500"
                class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300" required>
            @error('unit_count') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="space-y-3 pt-2">
            <label class="text-sm font-medium text-slate-700">Bu apartmanı hangi sıfatla yönetiyorsunuz?</label>

            <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="manager_type" value="external" class="mt-0.5 accent-emerald-600" {{ old('manager_type', 'external') === 'external' ? 'checked' : '' }}>
                <div>
                    <div class="text-sm font-semibold text-slate-800">Dışarıdan Yönetiyorum</div>
                    <div class="text-xs text-slate-500">Bu apartmanda oturmuyorum, profesyonel yönetici veya site yöneticisi olarak yönetiyorum.</div>
                </div>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="manager_type" value="owner" class="mt-0.5 accent-emerald-600" {{ old('manager_type') === 'owner' ? 'checked' : '' }}>
                <div>
                    <div class="text-sm font-semibold text-slate-800">Kat Maliki olarak yönetiyorum</div>
                    <div class="text-xs text-slate-500">Kendi dairemde oturuyor ve yöneticiliğini yapıyorum.</div>
                </div>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-xl border-2 border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="manager_type" value="tenant" class="mt-0.5 accent-emerald-600" {{ old('manager_type') === 'tenant' ? 'checked' : '' }}>
                <div>
                    <div class="text-sm font-semibold text-slate-800">Kiracı olarak yönetiyorum</div>
                    <div class="text-xs text-slate-500">Kiraladığım dairede oturuyor ve yöneticiliğini yapıyorum.</div>
                </div>
            </label>
        </div>

        <div id="unit-selection" class="{{ old('manager_type') === 'external' || !old('manager_type') ? 'hidden' : '' }}">
            <label class="text-sm font-medium text-slate-700">Hangi dairede oturuyorsunuz? <span class="text-red-500">*</span></label>
            <input type="number" name="manager_unit_no" value="{{ old('manager_unit_no') }}" min="1"
                class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
            @error('manager_unit_no') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <button type="submit"
            class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
            Apartmanı Oluştur ve Başla →
        </button>
    </form>
<script>
    document.querySelectorAll('input[name="manager_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const unitSelection = document.getElementById('unit-selection');
            if (this.value === 'external') {
                unitSelection.classList.add('hidden');
                document.querySelector('input[name="manager_unit_no"]').removeAttribute('required');
            } else {
                unitSelection.classList.remove('hidden');
                document.querySelector('input[name="manager_unit_no"]').setAttribute('required', 'required');
            }
        });
    });
</script>
@endsection
