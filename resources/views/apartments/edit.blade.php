@extends('layouts.app')

@section('title', 'Bilgileri Düzenle')

@section('content')
    @php
        $isSubscriber = request()->routeIs('subscriber.*');
        $provinces = explode(',', 'Adana,Adıyaman,Afyonkarahisar,Ağrı,Amasya,Ankara,Antalya,Artvin,Aydın,Balıkesir,'
            . 'Bilecik,Bingöl,Bitlis,Bolu,Burdur,Bursa,Çanakkale,Çankırı,Çorum,Denizli,'
            . 'Diyarbakır,Edirne,Elazığ,Erzincan,Erzurum,Eskişehir,Gaziantep,Giresun,Gümüşhane,Hakkari,'
            . 'Hatay,Isparta,Mersin,İstanbul,İzmir,Kars,Kastamonu,Kayseri,Kırklareli,Kırşehir,'
            . 'Kocaeli,Konya,Kütahya,Malatya,Manisa,Kahramanmaraş,Mardin,Muğla,Muş,Nevşehir,'
            . 'Niğde,Ordu,Rize,Sakarya,Samsun,Siirt,Sinop,Sivas,Tekirdağ,Tokat,'
            . 'Trabzon,Tunceli,Şanlıurfa,Uşak,Van,Yozgat,Zonguldak,Aksaray,Bayburt,Karaman,'
            . 'Kırıkkale,Batman,Şırnak,Bartın,Ardahan,Iğdır,Yalova,Karabük,Kilis,Osmaniye,Düzce');
        $selectedProvince = old('province', $apartment->province);
    @endphp

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Bilgileri Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }}</p>
    </div>

    <form method="POST" action="{{ $isSubscriber ? route('subscriber.apartments.update', $apartment) : route('apartments.update', $apartment) }}" class="max-w-2xl space-y-4">
        @csrf
        @method('PUT')

        <div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
            <div>
                <label class="text-sm font-medium text-slate-700">Apartman Adı</label>
                <input name="name" value="{{ old('name', $apartment->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-700">İl</label>
                    <select name="province" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="" @selected($selectedProvince === null || $selectedProvince === '')>İl seçin</option>
                        @if ($selectedProvince && ! in_array($selectedProvince, $provinces, true))
                            <option value="{{ $selectedProvince }}" selected>{{ $selectedProvince }}</option>
                        @endif
                        @foreach ($provinces as $province)
                            <option value="{{ $province }}" @selected($selectedProvince === $province)>{{ $province }}</option>
                        @endforeach
                    </select>
                    @error('province') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">İlçe</label>
                    <input name="district" value="{{ old('district', $apartment->district) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @error('district') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Adres</label>
                <textarea name="address" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" rows="2" required>{{ old('address', $apartment->address) }}</textarea>
                @error('address') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-sm font-semibold text-slate-900">Kayıt bilgileri</h2>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Daire sayısı</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->unit_count }}</dd>
                </div>
                @if ($apartment->code)
                    <div>
                        <dt class="text-slate-500">Apartman kodu</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ $apartment->code }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-slate-500">Kayıt tarihi</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->created_at?->format('d.m.Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Kurulum tarihi</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->setup_completed_at?->format('d.m.Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Yönetici</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->user?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Yönetici dairesi</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->managerUnit?->unit_no ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Kayıt durumu</dt>
                    <dd class="mt-1 font-medium text-slate-900">{{ $apartment->is_active ? 'Aktif' : 'Pasif' }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ $isSubscriber ? route('subscriber.apartments.show', $apartment) : route('apartments.show', $apartment) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</a>
            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kaydet</button>
        </div>
    </form>
@endsection
