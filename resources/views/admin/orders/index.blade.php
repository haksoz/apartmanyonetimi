@extends('layouts.app')

@section('title', 'Siparişler')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Siparişler</h1>
        <p class="text-sm text-slate-500">Bu ticari işlem ne durumda?</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-center gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Sipariş no, apartman, ödeyen veya fatura alıcısı" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-80">
            <select name="status" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="">Sipariş: Tümü</option>
                <option value="pending" @selected($status === 'pending')>Bekliyor</option>
                <option value="active" @selected($status === 'active')>Onaylandı</option>
                <option value="cancelled" @selected($status === 'cancelled')>İptal</option>
            </select>
            <select name="payment" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                <option value="">Ödeme: Tümü</option>
                <option value="collected" @selected($payment === 'collected')>Tahsil edildi</option>
                <option value="pending" @selected($payment === 'pending')>Bekliyor</option>
                <option value="unpaid" @selected($payment === 'unpaid')>Ödenmedi</option>
            </select>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search !== '' || $status !== '' || $payment !== '')
                <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Temizle</a>
            @endif
        </form>
    </div>

    <div class="space-y-2 md:hidden">
        @forelse ($orders as $order)
            @php
                $apartments = $order->items
                    ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
                    ->filter()
                    ->unique()
                    ->values();
            @endphp
            <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-mono font-semibold text-slate-900">{{ $order->order_number ?: '—' }}</p>
                    <p class="text-xs text-slate-500">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</p>
                    <p class="text-sm text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</p>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    {{ $order->subscription?->subscription_no ?: '—' }}
                    <span class="text-slate-300"> · </span>
                    {{ $apartments->isNotEmpty() ? $apartments->join(', ') : '—' }}
                    <span class="text-slate-300"> · </span>
                    {{ $order->user?->name ?: '—' }}
                    <span class="text-slate-300"> · </span>
                    {{ $order->billing_legal_name ?: ($order->billing_label ?: '—') }}
                    <span class="text-slate-300"> · </span>
                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                </p>
                <div class="mt-1 flex flex-wrap gap-2">
                    @include('admin.orders.partials.badges', ['order' => $order])
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500">Sipariş yok.</div>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Ödeyen</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Fatura alıcısı</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt Tarihi</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($orders as $order)
                    @php
                        $apartments = $order->items
                            ->map(fn ($item) => $item->apartment?->name ?? $item->apartment_name)
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-mono font-medium text-slate-900">{{ $order->order_number ?: '—' }}</div>
                            <div class="text-xs text-slate-500">{{ $order->subscription?->subscription_no ?: '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $apartments->isNotEmpty() ? $apartments->join(', ') : '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $order->user?->name ?: '—' }}</div>
                            @if ($order->user?->email)
                                <div class="text-xs text-slate-500">{{ $order->user->email }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (filled($order->billing_legal_name) || filled($order->billing_label))
                                <div class="font-medium text-slate-900">{{ $order->billing_legal_name ?: $order->billing_label }}</div>
                                @if (filled($order->billing_label) && filled($order->billing_legal_name) && $order->billing_label !== $order->billing_legal_name)
                                    <div class="text-xs text-slate-500">{{ $order->billing_label }}</div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @include('admin.orders.partials.badges', ['order' => $order])
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">Sipariş yok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
