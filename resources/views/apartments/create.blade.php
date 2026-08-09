@extends('layouts.app')

@section('content')
    @include('apartments.wizard._steps', ['activeStep' => 1])

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Yeni Apartman</h1>
        <p class="mt-1 text-sm text-slate-500">Daire sayısına göre daire ve hesap kayıtları otomatik oluşturulur.</p>
    </div>

    <form method="POST" action="{{ request()->routeIs('subscriber.*') ? route('subscriber.apartments.store') : route('apartments.store') }}" class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div class="space-y-5">
            <div>
                <label class="text-sm font-medium text-slate-700">Apartman Adı</label>
                <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Adres</label>
                <textarea name="address" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" rows="3" required>{{ old('address') }}</textarea>
                @error('address') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                @php
                    $provinces = explode(',', 'Adana,Adıyaman,Afyonkarahisar,Ağrı,Amasya,Ankara,Antalya,Artvin,Aydın,Balıkesir,'
                        . 'Bilecik,Bingöl,Bitlis,Bolu,Burdur,Bursa,Çanakkale,Çankırı,Çorum,Denizli,'
                        . 'Diyarbakır,Edirne,Elazığ,Erzincan,Erzurum,Eskişehir,Gaziantep,Giresun,Gümüşhane,Hakkari,'
                        . 'Hatay,Isparta,Mersin,İstanbul,İzmir,Kars,Kastamonu,Kayseri,Kırklareli,Kırşehir,'
                        . 'Kocaeli,Konya,Kütahya,Malatya,Manisa,Kahramanmaraş,Mardin,Muğla,Muş,Nevşehir,'
                        . 'Niğde,Ordu,Rize,Sakarya,Samsun,Siirt,Sinop,Sivas,Tekirdağ,Tokat,'
                        . 'Trabzon,Tunceli,Şanlıurfa,Uşak,Van,Yozgat,Zonguldak,Aksaray,Bayburt,Karaman,'
                        . 'Kırıkkale,Batman,Şırnak,Bartın,Ardahan,Iğdır,Yalova,Karabük,Kilis,Osmaniye,Düzce');
                @endphp
                <label class="text-sm font-medium text-slate-700">İl</label>
                <select name="province" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                    <option value="" disabled {{ old('province') ? '' : 'selected' }}>İl seçin</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province }}" {{ old('province') == $province ? 'selected' : '' }}>{{ $province }}</option>
                    @endforeach
                </select>
                @error('province') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">İlçe</label>
                <input name="district" value="{{ old('district') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
                @error('district') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Daire Sayısı</label>
                <input type="number" name="unit_count" value="{{ old('unit_count') }}" min="1" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                @error('unit_count') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Daire Hesaplarının Açılış Tarihi</label>
                <input type="date" name="account_opening_date" value="{{ old('account_opening_date', now()->toDateString()) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" required>
                @error('account_opening_date') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ request()->routeIs('subscriber.*') ? route('subscriber.dashboard') : route('apartments.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Vazgeç</a>
            <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Oluştur</button>
        </div>
    </form>
@endsection
