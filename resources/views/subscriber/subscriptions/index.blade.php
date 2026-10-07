@extends('layouts.app')

@section('title', 'Siparişlerim')

@section('content_width', 'max-w-none')

@section('content')
    <div>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Siparişlerim</h1>
                <p class="text-sm text-slate-500">Abonelik yenileme ve yükseltme talepleriniz.</p>
            </div>
            <a href="{{ route('subscriber.subscriptions.create', ['type' => 'renew']) }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Sipariş</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Sipariş No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Tarih</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Hizmet</th>
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
                            <td class="px-4 py-3 font-medium text-slate-900">
                                @if ($subscription->items->isNotEmpty())
                                    @foreach ($subscription->items as $item)
                                        <div>{{ $item->apartment_name }} · {{ $item->unit_count }} daire AidatCep Kullanım / {{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</div>
                                    @endforeach
                                @else
                                    Eski paket kaydı
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ number_format($subscription->price, 2) }} ₺</td>
                            <td class="px-4 py-3">
                                @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING && $subscription->hasPaymentProof())
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Onay bekliyor</span>
                                @elseif ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Ödeme bekliyor</span>
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
                                @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING && ! $subscription->hasPaymentProof())
                                    <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="ml-2 text-sm font-semibold text-amber-600 hover:text-amber-700">Ödeme Gir</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">Henüz bir siparişiniz bulunmuyor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 md:hidden">
            @forelse ($subscriptions as $subscription)
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm font-semibold text-slate-900">{{ $subscription->order_number ?? '-' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $subscription->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                        @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING && $subscription->hasPaymentProof())
                            <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Onay bekliyor</span>
                        @elseif ($subscription->status === App\Models\UserSubscription::STATUS_PENDING)
                            <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">Ödeme bekliyor</span>
                        @elseif ($subscription->status === App\Models\UserSubscription::STATUS_ACTIVE)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                        @elseif ($subscription->status === App\Models\UserSubscription::STATUS_CANCELLED)
                            <span class="inline-flex rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700">İptal Edildi</span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-900">
                        @if ($subscription->items->isNotEmpty())
                            @foreach ($subscription->items as $item)
                                <span class="block">{{ $item->apartment_name }} · {{ $item->unit_count }} daire AidatCep Kullanım / {{ $subscription->period === 'yearly' ? 'Yıllık' : 'Aylık' }}</span>
                            @endforeach
                        @else
                            Eski paket kaydı
                        @endif
                    </p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format($subscription->price, 2) }} ₺</p>
                    @if ($subscription->receipt_reference)
                        <p class="mt-2 text-sm text-slate-600">{{ $subscription->receipt_reference }}</p>
                    @endif
                    @if ($subscription->receipt_path)
                        <a href="{{ Storage::url($subscription->receipt_path) }}" target="_blank" class="mt-1 inline-flex text-xs font-medium text-emerald-600 hover:text-emerald-700">Dekont görüntüle</a>
                    @endif
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                        @if ($subscription->status === App\Models\UserSubscription::STATUS_PENDING && ! $subscription->hasPaymentProof())
                            <a href="{{ route('subscriber.subscriptions.receipt', $subscription) }}" class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-amber-600">Ödeme Gir</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">Henüz bir siparişiniz bulunmuyor.</div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $subscriptions->links() }}
        </div>
    </div>
@endsection
