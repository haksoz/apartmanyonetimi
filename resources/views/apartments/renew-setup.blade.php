@extends('layouts.app')

@section('wizard_steps')
    <div class="-mt-4">
        @include('apartments.wizard._steps', ['activeStep' => 1])
    </div>
@endsection

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-bold text-slate-950 sm:text-2xl">Kurulumu yenile</h1>
        <p class="mt-1 text-sm text-slate-500">Apartman bilgileri yeniden alınır. Abonelik numarası aynı dilimde durur. Ücretli hak başka apartmana taşınmaz.</p>
    </div>

    <form method="POST" action="{{ route('apartments.renew-setup', $apartment) }}" class="max-w-2xl rounded-2xl bg-white p-4 shadow-sm sm:p-5">
        @csrf
        <div class="space-y-3">
            <div>
                <label class="text-sm font-medium text-slate-700">Apartman Adı</label>
                <input name="name" value="{{ old('name', $apartment->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                @error('name') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            @php
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
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-700">İl</label>
                    <select name="province" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option value="" disabled {{ $selectedProvince ? '' : 'selected' }}>İl seçin</option>
                        @foreach ($provinces as $province)
                            <option value="{{ $province }}" {{ $selectedProvince == $province ? 'selected' : '' }}>{{ $province }}</option>
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
            <div>
                <label class="text-sm font-medium text-slate-700">Daire Sayısı</label>
                <input type="number" id="unit-count" name="unit_count" value="{{ old('unit_count', $apartment->unit_count) }}" min="1" max="500" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                @error('unit_count') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
                <p class="mt-1 text-xs text-slate-500">Aynı ücret diliminde abonelik bu apartmanda kalır. Dilim değişirse eski apartman pasife alınır.</p>
                <p id="quote-notice" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanmaktadır. Talebiniz alınmıştır. Temsilcimiz sizinle iletişime geçerek size özel teklifinizi paylaşacaktır.</p>
            </div>
            @error('accept_new_free_apartment')
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <label class="flex items-start gap-2 text-sm text-amber-800">
                        <input type="checkbox" name="accept_new_free_apartment" value="1" class="mt-1">
                        <span>{{ $message }}</span>
                    </label>
                </div>
            @enderror
            <div id="resident-consent">
                @include('partials.accept-resident-data')
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Daire Hesaplarının Açılış Tarihi</label>
                <input type="date" name="account_opening_date" value="{{ old('account_opening_date', now()->toDateString()) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                @error('account_opening_date') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                <label class="flex items-start gap-2 text-sm font-medium text-slate-800">
                    <input type="checkbox" name="skip_archive_check" value="1" class="mt-1" @checked(old('skip_archive_check'))>
                    <span>Yedekleme altyapısı kontrolünü atla</span>
                </label>
                <p class="mt-2 text-sm text-amber-800">Bu işlem için henüz otomatik yedekleme ve geri yükleme altyapısı bulunmamaktadır. Devam ederseniz seçilen veriler kalıcı olarak silinebilir ve geri getirilemeyebilir. Bu işlemin öncesinde verilerin yedeği alınmayacaktır.</p>
                <p class="mt-2 text-xs text-slate-600">Bu kutu, aynı apartmanda veri silinerek kurulum yenilenecekse zorunludur. Dilim değişiminde eski veri silinmediği için aranmaz.</p>
                @error('skip_archive_check') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Şifreniz</label>
                <input type="password" name="current_password" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                @error('current_password') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Onay metni</label>
                <input type="text" name="confirmation" value="{{ old('confirmation') }}" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="kurulumun yenilenmesini kabul ediyorum" required>
                <span class="mt-1 block text-xs text-slate-500">"kurulumun yenilenmesini kabul ediyorum" yazın.</span>
                @error('confirmation') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mt-4 flex justify-end gap-3">
            <a href="{{ route('apartments.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Vazgeç</a>
            <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Devam</button>
        </div>
        <script>
            (function () {
                const count = document.getElementById('unit-count');
                const notice = document.getElementById('quote-notice');
                const resident = document.getElementById('resident-consent');
                if (!count || !notice || !resident) return;
                const inputs = resident.querySelectorAll('input[type="checkbox"]');
                const sync = function () {
                    const quote = Number(count.value) >= 101;
                    notice.classList.toggle('hidden', !quote);
                    resident.classList.toggle('hidden', quote);
                    inputs.forEach(function (input) { input.required = !quote; });
                };
                count.addEventListener('input', sync);
                sync();
            })();
        </script>
    </form>
@endsection
