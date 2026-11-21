@extends('layouts.app')

@section('title', 'Admin Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Admin Paneli</h1>
        <p class="text-sm text-slate-500">Operasyonel özet ve yönetim.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-sky-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-sky-50 p-3">
                    <svg class="w-6 h-6 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Müşteriler ({{ $customerCount }})</div>
                    <div class="text-sm text-slate-500">Kayıtlı müşteriyi ve ilişkilerini gör.</div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-emerald-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-emerald-50 p-3">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641m-3.958-5.599c.351.351.645.748.876 1.185M9 13.5V9.75a6 6 0 0112 0v3"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Abonelikler ({{ $subscriptionCount }})</div>
                    <div class="text-sm text-slate-500">Apartmanın abonelik geçmişini takip et.</div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.orders.index') }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-amber-50 p-3">
                    <svg class="w-6 h-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Siparişler ({{ $orderCount }})</div>
                    <div class="text-sm text-slate-500">Siparişin ve tahsilatın durumunu takip et.</div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <span class="text-amber-700">Bekleyen: {{ $pendingOrderCount }}</span>
                        <span class="text-emerald-700">Aktif: {{ $activeOrderCount }}</span>
                        <span class="text-slate-500">İptal: {{ $cancelledOrderCount }}</span>
                    </div>
                </div>
            </div>
        </a>
    </div>

    @php
        $segments = [
            'monthly_3' => 'Aylık · Son 3 gün',
            'monthly_7' => 'Aylık · Son 7 gün',
            'yearly_7' => 'Yıllık · Son 7 gün',
            'yearly_30' => 'Yıllık · 8–30 gün kaldı',
            'expired_7' => 'Süresi dolmuş · İlk 7 gün',
            'expired_30' => 'Süresi dolmuş · 8–30 gün',
            'dormant' => 'Süresi dolmuş · 30+ gün',
        ];
    @endphp
    <section class="mt-8 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Abonelik takibi</h2>
            <p class="text-sm text-slate-500">Ücretli dönemlere göre apartman bazında takip. Yenilenmiş abonelikler hariçtir. Aylık son 3 gün, son 7 güne dahildir. Süresi dolan gruplar tüm ücretli dönemleri kapsar.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($segments as $key => $label)
                <a href="{{ route('admin.dashboard', ['follow_up' => $key]) }}" @if($segment === $key) aria-current="page" @endif class="rounded-xl border p-5 {{ $segment === $key ? 'border-sky-400 bg-sky-50' : 'border-slate-200 bg-white hover:border-sky-300' }}">
                    <div class="text-sm text-slate-600">{{ $label }}</div>
                    <div class="mt-2 text-3xl font-bold text-slate-900">{{ $followUpCounts[$key] }}</div>
                    <div class="mt-2 text-xs text-sky-700">Abonelikleri gör →</div>
                </a>
            @endforeach
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 p-4">
                <h3 class="font-semibold text-slate-900">{{ $segments[$segment] }}</h3>
                <p class="text-sm text-slate-500">{{ str_starts_with($segment, 'monthly_') || str_starts_with($segment, 'yearly_') ? 'Yenileme ihtiyacını görüşmek için abonelik detayını açın.' : 'Yenileme yapılmayan apartmanları inceleyin; bu liste giriş veya kullanım sıklığını ölçmez.' }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-3">Abonelik / Apartman</th><th class="px-4 py-3">Ücretli dönem bitişi</th><th class="px-4 py-3">Takip</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($attentionSubscriptions as $subscription)
                            <tr>
                                <td class="px-4 py-3"><a class="font-medium text-sky-700 hover:underline" href="{{ route('admin.subscriptions.show', $subscription) }}">{{ $subscription->subscription_no }}</a><div class="text-slate-500">{{ $subscription->apartment?->name ?: $subscription->apartment_name ?: 'Apartman kaydı bulunmuyor' }}</div></td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($subscription->paid_until)->format('d.m.Y H:i') }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Carbon::parse($subscription->paid_until)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-slate-500">Bu grupta takip gerektiren abonelik bulunmuyor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $attentionSubscriptions->links() }}</div>
        </div>
    </section>

    <section class="mt-8 space-y-4">
        <h2 class="text-lg font-semibold text-slate-900">Siparişlerde öncelikli takip</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div class="text-sm text-amber-800">Bekleyen sipariş tutarı</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($pendingOrderAmount, 2, ',', '.') }} TL</div><p class="mt-2 text-xs text-slate-600">{{ $pendingOrderCount }} siparişin toplam bedeli; tahsilat değildir.</p>
            </a>
            <div class="rounded-xl border border-slate-200 bg-white p-5"><div class="text-sm text-slate-600">48 saati aşan bekleme</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $staleOrderCount }}</div><p class="mt-2 text-xs text-slate-500">Ödeme veya onay sürecini kontrol edin. Aşağıda en eski kayıtlar önce gösterilir.</p></div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-5"><div class="text-sm text-rose-800">Ödeme incelemesi gerekiyor</div><div class="mt-2 text-2xl font-bold text-slate-900">{{ $reviewOrderCount }}</div><p class="mt-2 text-xs text-slate-600">İnceleme işareti bulunan siparişler aşağıda önceliklidir.</p></div>
        </div>
        <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
            @forelse ($attentionOrders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-slate-50">
                    <div><div class="font-medium text-sky-700">{{ $order->order_number ?: 'Sipariş #'.$order->id }} · {{ $order->user?->name ?: 'Müşteri kaydı bulunmuyor' }}</div><div class="mt-1 text-xs text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->created_at->diffForHumans() }}</div></div>
                    <div class="text-sm {{ $order->needs_review ? 'text-rose-700' : 'text-amber-700' }}">{{ $order->needs_review ? 'Ödemeyi incele' : 'Bekleyen siparişi incele' }} →</div>
                </a>
            @empty
                <p class="p-5 text-sm text-slate-500">Takip gerektiren sipariş bulunmuyor.</p>
            @endforelse
            <div class="p-4 text-xs text-slate-500">En fazla 8 kayıt gösterilir. <a class="text-sky-700 hover:underline" href="{{ route('admin.orders.index') }}">Tüm siparişleri aç →</a></div>
        </div>
    </section>
@endsection
