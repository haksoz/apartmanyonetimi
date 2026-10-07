@extends('layouts.app')

@section('title', 'Müşteriler')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Müşteriler</h1>
            <p class="text-sm text-slate-500">Kayıtlı müşteriler ve ilişkileri.</p>
        </div>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Ad, e-posta veya telefon" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-80">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search !== '')
                <a href="{{ route('admin.customers.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Temizle</a>
            @endif
        </form>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse ($customers as $customer)
            <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-semibold text-slate-900">{{ $customer->name ?: '—' }}</p>
                    <p class="text-sm text-slate-600">{{ $customer->email ?: '—' }}</p>
                    @if ($customer->phone)
                        <p class="text-sm text-slate-600">{{ $customer->phone }}</p>
                    @endif
                    <p class="text-xs text-slate-500">{{ $customer->created_at?->format('d.m.Y') ?? '—' }}</p>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    Apartman <span class="font-medium text-slate-900">{{ $customer->apartment_count }}</span>
                    <span class="text-slate-300"> · </span>
                    Aktif abonelik <span class="font-medium text-slate-900">{{ $customer->active_subscription_count }}</span>
                    <span class="text-slate-300"> · </span>
                    Bekleyen sipariş <span class="font-medium text-slate-900">{{ $customer->pending_order_count }}</span>
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
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Aktif abonelik</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Bekleyen sipariş</th>
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
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $customer->phone ?: '-' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $customer->apartment_count }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $customer->active_subscription_count }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $customer->pending_order_count }}</td>
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
