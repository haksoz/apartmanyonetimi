@extends('layouts.app')

@section('title', 'Apartmanlar')

@section('content')
    <h1 class="text-2xl font-bold text-slate-900">Apartmanlar</h1>
    <p class="mt-1 text-sm text-slate-500">Apartman kimlikleri, güncel yöneticiler ve bağlı abonelikler.</p>
    <form method="GET" action="{{ route('admin.apartments.index') }}" class="my-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" aria-label="Apartman ara" placeholder="Apartman adı, kodu, yönetici veya ABN" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-96">
        <select name="status" aria-label="Apartman durumu" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
            <option value="">Durum: Tümü</option><option value="active" @selected($status === 'active')>Aktif</option><option value="inactive" @selected($status === 'inactive')>Pasif</option>
        </select>
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ara</button>
        @if ($search !== '' || $status !== '')<a href="{{ route('admin.apartments.index') }}" class="text-sm font-semibold text-slate-600">Temizle</a>@endif
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600"><tr>
                <th class="px-4 py-3">Apartman / Kod</th><th class="px-4 py-3">Güncel yöneticiler</th><th class="px-4 py-3">Abonelik</th><th class="px-4 py-3">Daire</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3 text-right">İşlem</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($apartments as $apartment)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('admin.apartments.show', $apartment) }}" class="font-semibold text-slate-900 hover:text-emerald-700">{{ $apartment->name }}</a><div class="mt-1 text-xs text-slate-500">{{ $apartment->code }} · ID {{ $apartment->id }}</div></td>
                        <td class="px-4 py-3">
                            @forelse ($apartment->managers as $manager)
                                <div>@include('admin.apartments.partials.person', ['person' => $manager])</div>
                            @empty
                                <span class="text-slate-500">Yönetici yok</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3">
                            @forelse ($apartment->subscriptions as $subscription)
                                <div><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="font-mono text-xs font-semibold text-emerald-700">{{ $subscription->subscription_no }}</a>@if ($subscription->status === \App\Models\Subscription::STATUS_ENDED)<span class="ml-1 text-xs text-slate-500">Sona erdi</span>@endif</div>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3">{{ $apartment->unit_count }}</td>
                        <td class="px-4 py-3"><span class="{{ $apartment->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $apartment->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                @if ($apartment->is_active)
                                    <form method="POST" action="{{ route('current-apartment.update') }}">
                                        @csrf
                                        <input type="hidden" name="apartment_id" value="{{ $apartment->id }}">
                                        <button type="submit" aria-label="{{ $apartment->name }} apartmanını yönet" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Yönet</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.apartments.show', $apartment) }}" class="font-semibold text-emerald-700">Detay</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Apartman bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $apartments->links() }}</div>
@endsection
