@extends('layouts.app')

@section('title', 'Siparişlerim')

@section('content')
    <div class="max-w-5xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Siparişlerim</h1>
                <p class="text-sm text-slate-500">Abonelik yenileme ve yükseltme talepleriniz.</p>
            </div>
            <a href="{{ route('subscriber.subscriptions.create', ['type' => 'renew']) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Sipariş</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarih</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Paket</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tutar</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Durum</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Dekont / Referans</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse ($subscriptions as $subscription)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-sm font-medium text-slate-900">{{ $subscription->order_number ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $subscription->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $subscription->package->name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ number_format($subscription->price, 2) }} ₺</td>
                            <td class="px-4 py-3">
                                @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Bekliyor</span>
                                @elseif ($subscription->status === App\Models\UserSubscription::STATUS_ACTIVE)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                @elseif ($subscription->status === App\Models\UserSubscription::STATUS_CANCELLED)
                                    <span class="inline-flex rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700">İptal Edildi</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                @if ($subscription->receipt_reference)
                                    <span class="block">{{ $subscription->receipt_reference }}</span>
                                @endif
                                @if ($subscription->receipt_path)
                                    <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="text-xs font-medium text-emerald-600 hover:text-emerald-700">Dekont görüntüle</a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                                @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
                                    <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="ml-2 text-sm font-semibold text-amber-600 hover:text-amber-700">Ödeme bilgisi gir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">Henüz bir siparişiniz bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $subscriptions->links() }}
        </div>
    </div>
@endsection
