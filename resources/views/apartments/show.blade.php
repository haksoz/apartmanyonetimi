@extends('layouts.app')

@section('content')
    {{-- Başlık --}}
    <div class="mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $apartment->name }} — Ayarlar</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</p>
            <p class="mt-1 text-xs text-slate-400">Apartman ID: {{ $apartment->id }}</p>
            @if ($apartment->managerUnit)
                <p class="mt-1 text-xs text-emerald-700 font-medium">
                    Yönetici: {{ str_pad($apartment->managerUnit->unit_no, 2, '0', STR_PAD_LEFT) }} No.lu Daire
                </p>
            @else
                <p class="mt-1 text-xs text-slate-400">Yönetici dışardan yönetiyor</p>
            @endif
        </div>
    </div>

    {{-- Özet Kartları --}}
    <div class="grid gap-4 md:grid-cols-3 mb-8">
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Daire Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->units->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Hesap Sayısı</div><div class="mt-2 text-2xl font-bold">{{ $apartment->accounts->count() }}</div></div>
        <div class="rounded-2xl bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Durum</div><div class="mt-2 text-2xl font-bold">{{ $apartment->is_active ? 'Aktif' : 'Pasif' }}</div></div>
    </div>

    {{-- Ayar Bölümleri --}}
    <div class="space-y-6">

        {{-- Veri İçe Aktarma --}}
        @if($isOwner)
        <div class="rounded-2xl bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-base font-semibold text-slate-900">Veri İçe Aktarma</h2>
                <p class="mt-0.5 text-sm text-slate-500">Toplu hesap hareketlerini Excel ile içe aktarın veya mevcut içe aktarımları yönetin.</p>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Genel Hesap İçe Aktar</p>
                        <p class="text-xs text-slate-500">Excel dosyası ile toplu hesap hareketlerini sisteme aktarın.</p>
                    </div>
                    <a href="{{ route('accounts.bulk-import') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">İçe Aktar</a>
                </div>

                @if($hasImported)
                <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700">İçe Aktarılanları Sil</p>
                        <p class="text-xs text-slate-500">Daha önce içe aktarılmış tüm cari hareketler, aidatlar, giderler ve ödemeler silinir.</p>
                    </div>
                    <form method="POST" action="{{ route('accounts.imported.destroy-all') }}" onsubmit="return confirm('Tüm içe aktarılmış cari hareketler ve bağlı kayıtlar silinecek. Bu işlem geri alınamaz. Emin misiniz?')">
                        @csrf
                        <button type="submit" class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                    </form>
                </div>
                @endif

                <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Tüm Verileri Sil</p>
                        <p class="text-xs text-slate-500">Hesap bilgileri hariç tüm veriler silinir. Daire sayısı değişmez.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('destroy-all-modal').classList.remove('hidden')" class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Tüm Verileri Sil</button>
                </div>

                <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Apartmanı Sil ve Yenile</p>
                        <p class="text-xs text-slate-500">Mevcut apartmanı silip yeni apartman oluşturun.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('reset-and-renew-modal').classList.remove('hidden')" class="rounded-xl border border-orange-300 bg-white px-4 py-2 text-sm font-semibold text-orange-600 hover:bg-orange-50">Apartmanı Sil ve Yenile</button>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- Tüm Verileri Sil Modal --}}
    <div id="destroy-all-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <form method="POST" action="{{ route('apartments.destroy-all', $apartment) }}" class="max-w-md rounded-2xl bg-white p-6 shadow-xl w-full mx-4">
            @csrf
            <div class="mb-4">
                <h3 class="text-lg font-bold text-slate-900">Tüm Verileri Sil</h3>
                <p class="mt-2 text-sm text-slate-600">Bu işlem geri alınamaz. Hesap bilgileri hariç tüm veriler silinir. Daire sayısı değişmez.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Onay metni</label>
                <input type="text" name="confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="tüm verilerin silinmesini kabul ediyorum">
                <p class="mt-1 text-xs text-slate-500">Onaylamak için lütfen "tüm verilerin silinmesini kabul ediyorum" yazın.</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('destroy-all-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Sil</button>
            </div>
        </form>
    </div>

    {{-- Apartmanı Sil ve Yenile Modal --}}
    <div id="reset-and-renew-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <form method="POST" action="{{ route('apartments.reset-and-renew', $apartment) }}" class="max-w-md rounded-2xl bg-white p-6 shadow-xl w-full mx-4">
            @csrf
            <div class="mb-4">
                <h3 class="text-lg font-bold text-slate-900">Apartmanı Sil ve Yenile</h3>
                <p class="mt-2 text-sm text-slate-600">Bu işlem geri alınamaz. Mevcut apartman silinecek ve yeni apartman oluşturma sayfasına yönlendirileceksiniz.</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Onay metni</label>
                <input type="text" name="confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="apartmanın silinmesini kabul ediyorum">
                <p class="mt-1 text-xs text-slate-500">Onaylamak için lütfen "apartmanın silinmesini kabul ediyorum" yazın.</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('reset-and-renew-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</button>
                <button type="submit" class="rounded-xl bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700">Sil ve Yenile</button>
            </div>
        </form>
    </div>
@endsection
