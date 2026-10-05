@extends('layouts.app')

@section('title', 'Admin Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Admin Paneli</h1>
        <p class="text-sm text-slate-500">Operasyonel özet ve yönetim.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="text-sm font-medium text-slate-500">Yönetici</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $managerCount }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="text-sm font-medium text-slate-500">Apartman</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $apartmentCount }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="text-sm font-medium text-slate-500">Aktif Abonelik</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $activeSubscriptionCount }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="text-sm font-medium text-slate-500">Süresi Dolmuş Abonelik</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $expiredSubscriptionCount }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Apartman Yönetimi</h2>
        <p class="mt-1 text-sm text-slate-500">Yönetmek istediğiniz apartmanı seçin veya apartman yönetim ekranına geçin.</p>

        @if ($apartments->isEmpty())
            <p class="mt-4 text-sm text-slate-500">Henüz apartman yok.</p>
        @else
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($apartments as $apartment)
                    <form method="POST" action="{{ route('current-apartment.update') }}" class="rounded-xl border border-slate-200 p-4">
                        @csrf
                        <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                        <div class="font-semibold text-slate-900">{{ $apartment->name }}</div>
                        <div class="text-sm text-slate-500">{{ $apartment->address ?: 'Adres girilmedi' }}</div>
                        <div class="mt-1 text-sm text-slate-600">Yönetici: <span class="font-medium">{{ $apartment->user?->name ?? 'Belirtilmemiş' }}</span></div>
                        <div class="mt-2 text-sm text-slate-500">{{ $apartment->unit_count }} daire</div>
                        <button type="submit" class="mt-3 w-full rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Bu Apartmanla Devam Et</button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('current-apartment.select') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Apartman Yönetimine Git →</a>
            <a href="{{ route('admin.archived-apartments.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-700">Silinmiş apartmanlar</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-emerald-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-emerald-50 p-3">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641m-3.958-5.599c.351.351.645.748.876 1.185M9 13.5V9.75a6 6 0 0112 0v3"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Abonelikler</div>
                    <div class="text-sm text-slate-500">Kullanıcı aboneliklerini ve kotalarını takip et.</div>
                </div>
            </div>
        </a>
    </div>
@endsection
