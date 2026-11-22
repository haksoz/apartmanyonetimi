@extends('layouts.app')

@section('title', 'Siparişler')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
        <h1 class="text-2xl font-bold text-slate-900">Siparişler</h1>
        <p class="text-sm text-slate-500">Bu ticari işlem ne durumda?</p>
        </div>
        <a href="{{ route('admin.order-invoices.xml.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Satış XML gir</a>
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
                <option value="abandoned" @selected($status === 'abandoned')>Yarım bırakıldı</option>
                <option value="deleted" @selected($status === 'deleted')>Pasif silinenler</option>
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
            <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                <div class="flex flex-wrap items-baseline gap-2">
                    <p class="font-mono font-semibold text-slate-900">@include('admin.orders.partials.number-with-apartment', ['order' => $order])</p>
                    <p class="text-xs text-slate-500">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</p>
                    <p class="text-sm text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</p>
                </div>
                <p class="mt-1 text-sm text-slate-600">@include('admin.orders.partials.service-summary', ['order' => $order])</p>
                <p class="mt-1 text-sm text-slate-600">
                    @include('admin.orders.partials.invoice-action', ['order' => $order])
                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Detay</a>
                    <span class="text-slate-300"> · </span>
                    @unless ($order->trashed())
                    <button type="button" onclick="openPurgeOrder(@js(route('admin.orders.destroy', $order)), @js($order->order_number ?: 'Sipariş'))" class="font-semibold text-red-700 hover:text-red-800">Pasif sil</button>
                    @endunless
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
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt Tarihi</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($orders as $order)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-mono font-medium text-slate-900">@include('admin.orders.partials.number-with-apartment', ['order' => $order])</div>
                            <div class="text-xs text-slate-500">@include('admin.orders.partials.service-summary', ['order' => $order])</div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ number_format((float) $order->price, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @include('admin.orders.partials.badges', ['order' => $order])
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $order->created_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @include('admin.orders.partials.invoice-action', ['order' => $order])
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                    @unless ($order->trashed())
                            <button type="button" onclick="openPurgeOrder(@js(route('admin.orders.destroy', $order)), @js($order->order_number ?: 'Sipariş'))" class="ml-3 text-sm font-semibold text-red-700 hover:text-red-800">Pasif sil</button>
                    @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Sipariş yok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>

    @foreach ($orders as $order)
        @if ($order->can_invoice)
            @include('admin.orders.partials.invoice-modal', ['order' => $order, 'modalId' => 'invoice-order-modal-'.$order->id])
        @endif
    @endforeach

    <div id="purge-order-modal" @class([
        'fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4',
        'hidden' => ! $errors->has('current_password') && ! $errors->has('order'),
    ])>
        <form method="POST" action="{{ old('purge_order_action', '#') }}" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            @csrf
            @method('DELETE')
            <input type="hidden" name="purge_order_action" value="{{ old('purge_order_action') }}">
            <h2 class="text-lg font-bold text-slate-900">Siparişi pasif sil</h2>
            <p id="purge-order-copy" class="mt-2 text-sm text-slate-600">Sipariş listeden kalkar. Kayıt veritabanında durur.</p>
            <label class="mt-4 block text-sm font-medium text-slate-700">
                Kendi parolanız
                <input type="password" name="current_password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-900">
                @error('current_password')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                @error('order')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('purge-order-modal').classList.add('hidden')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
                <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Pasif sil</button>
            </div>
        </form>
    </div>
    <script>
        function openPurgeOrder(action, number) {
            const modal = document.getElementById('purge-order-modal');
            const form = modal.querySelector('form');
            form.action = action;
            form.querySelector('input[name="purge_order_action"]').value = action;
            document.getElementById('purge-order-copy').textContent = number + ' listeden kalkar. Bu işlem PayTR ödemesini iptal etmez. Belirsiz girişimler ve tekrar ödeme koruması devam eder.';
            modal.classList.remove('hidden');
        }
    </script>
@endsection
