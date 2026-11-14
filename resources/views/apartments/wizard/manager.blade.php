@extends('layouts.apartment-setup')

@section('setup_width', '40rem')

@section('wizard_steps')
    @include('apartments.wizard._steps', ['activeStep' => 2])
@endsection

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Yönetici ve Daire Bilgileri</h1>
        <p class="mt-1 text-sm text-slate-500">Apartmandaki durumunuzu seçin. Daireniz varsa hesabınız mevcut üyeliğinize bağlanır ve portal erişiminiz açılır.</p>
    </div>

    <form method="POST" action="{{ route('apartments.wizard.manager.store', $apartment) }}" class="max-w-2xl space-y-5 rounded-2xl bg-white p-5 shadow-sm">
        @csrf
        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700">
            <p class="font-semibold">{{ auth()->user()->name }}</p>
            <p>{{ auth()->user()->email }}</p>
            @if (auth()->user()->phone)<p>{{ auth()->user()->phone }}</p>@endif
        </div>
        <div>
            <label for="manager_type" class="text-sm font-medium text-slate-700">Bu apartmandaki durumunuz nedir?</label>
            <select id="manager_type" name="manager_type" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                <option value="">Seçin</option>
                @foreach (['owner' => 'Kat malikiyim', 'tenant' => 'Kiracıyım', 'external' => 'Yalnızca yöneticiyim'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('manager_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('manager_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <fieldset id="manager-unit-fields" class="space-y-4">
            <div>
                <label for="unit_id" class="text-sm font-medium text-slate-700">Daireniz</label>
                <select id="unit_id" name="unit_id" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                    <option value="">Daire seçin</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>Daire {{ $unit->unit_no }}</option>
                    @endforeach
                </select>
                @error('unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="started_at" id="manager-date-label" class="text-sm font-medium text-slate-700">Başlangıç tarihi</label>
                <input type="date" id="started_at" name="started_at" min="{{ $openingDate }}" value="{{ old('started_at', $openingDate) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-500">Apartman kayıtlarında esas alınacak başlangıç tarihidir. Hesap açılış tarihinden ({{ \Illuminate\Support\Carbon::parse($openingDate)->format('d.m.Y') }}) önce olamaz.</p>
                <p id="manager-owner-history-note" class="mt-1 text-xs text-slate-500">Mülkiyetiniz hesap açılışından sonra başlıyorsa önceki kat maliki hesabı, başlangıç tarihinizden bir gün önce sonlandırılır ve sizin için yeni hesap açılır.</p>
                @error('started_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </fieldset>
        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet ve Devam Et</button>
        </div>
    </form>
    <script>
        (() => {
            const type = document.getElementById('manager_type');
            const fields = document.getElementById('manager-unit-fields');
            function sync() {
                const needsUnit = type.value !== '' && type.value !== 'external';
                fields.hidden = !needsUnit;
                fields.disabled = !needsUnit;
                document.getElementById('unit_id').required = needsUnit;
                document.getElementById('started_at').required = needsUnit;
                document.getElementById('manager-date-label').textContent = type.value === 'tenant' ? 'Kiracılık başlangıç tarihi' : 'Mülkiyet başlangıç tarihi';
                document.getElementById('manager-owner-history-note').hidden = type.value === 'tenant';
            }
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
