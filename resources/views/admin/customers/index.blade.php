@extends('layouts.app')

@section('title', 'Müşteriler')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Müşteriler</h1>
            <p class="text-sm text-slate-500">Ticari muhataplar. Diğer kişileri filtreyle açın.</p>
        </div>
    </div>

    @php
        $filters = [
            'muhataplar' => 'Muhataplar',
            'uyeler' => 'Üyeler',
            'tumu' => 'Tümü',
        ];
        $showsCommerce = $filter === 'muhataplar';
    @endphp

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($filters as $key => $label)
            <a href="{{ route('admin.customers.index', array_filter(['filtre' => $key === 'muhataplar' ? null : $key, 'search' => $search !== '' ? $search : null])) }}"
                class="rounded-full px-3 py-1.5 text-sm font-semibold {{ $filter === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-wrap items-center gap-3">
            @if ($filter !== 'muhataplar')
                <input type="hidden" name="filtre" value="{{ $filter }}">
            @endif
            <input type="text" name="search" value="{{ $search }}" placeholder="Ad, e-posta veya telefon" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-80">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search !== '')
                <a href="{{ route('admin.customers.index', $filter === 'muhataplar' ? [] : ['filtre' => $filter]) }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Temizle</a>
            @endif
        </form>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse ($customers as $customer)
            <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-semibold text-slate-900">{{ $customer->name ?: '—' }}</p>
                    @if ($customer->deactivated_at)
                        <p class="text-xs font-semibold text-slate-500">Pasif</p>
                    @endif
                    <p class="text-sm text-slate-600">{{ $customer->email ?: '—' }}</p>
                    @if ($customer->phone)
                        <p class="text-sm text-slate-600">{{ $customer->phone }}</p>
                    @endif
                    <p class="text-xs text-slate-500">{{ $customer->created_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($showsCommerce)
                        Apartman <span class="font-medium text-slate-900">{{ $customer->managed_apartment_count }}</span>
                        / {{ $customer->quotaOverride?->max_apartments ?? 'sınırsız' }}
                        <span class="text-slate-300"> · </span>
                        Aktif abonelik <span class="font-medium text-slate-900">{{ $customer->active_subscription_count }}</span>
                        <span class="text-slate-300"> · </span>
                        Bekleyen sipariş <span class="font-medium text-slate-900">{{ $customer->pending_order_count }}</span>
                    @else
                        @php
                            $roleLabel = ! $customer->activated_at
                                ? 'Davetli'
                                : (($customer->role === 'manager' || $customer->manages_building) ? 'Yönetici' : 'Üye');
                            $statusLabel = $customer->deactivated_at ? 'Pasif' : ($customer->activated_at ? 'Aktif' : 'Davetli');
                        @endphp
                        Rol <span class="font-medium text-slate-900">{{ $roleLabel }}</span>
                        <span class="text-slate-300"> · </span>
                        Durum <span class="font-medium text-slate-900">{{ $statusLabel }}</span>
                    @endif
                    <span class="text-slate-300"> · </span>
                    <a href="{{ route('admin.customers.show', $customer) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                </p>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Müşteri yok.</div>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Müşteri</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Telefon</th>
                    @if ($showsCommerce)
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Yönetilen / limit</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Aktif abonelik</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Bekleyen sipariş</th>
                    @else
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Rol</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    @endif
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt Tarihi</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($customers as $customer)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $customer->name ?: '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $customer->email ?: '—' }}</div>
                            @if ($customer->deactivated_at)
                                <div class="mt-1 text-xs font-semibold text-slate-500">Pasif</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $customer->phone ?: '-' }}</td>
                        @if ($showsCommerce)
                            <td class="px-4 py-3 text-slate-700">{{ $customer->managed_apartment_count }} / {{ $customer->quotaOverride?->max_apartments ?? 'sınırsız' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $customer->active_subscription_count }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $customer->pending_order_count }}</td>
                        @else
                            @php
                                $roleLabel = ! $customer->activated_at
                                    ? 'Davetli'
                                    : (($customer->role === 'manager' || $customer->manages_building) ? 'Yönetici' : 'Üye');
                                $statusLabel = $customer->deactivated_at ? 'Pasif' : ($customer->activated_at ? 'Aktif' : 'Davetli');
                            @endphp
                            <td class="px-4 py-3 text-slate-700">{{ $roleLabel }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $statusLabel }}</td>
                        @endif
                        <td class="px-4 py-3 text-slate-700">{{ $customer->created_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Müşteri yok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $customers->links() }}</div>
@endsection
