@extends('layouts.app')
@section('title', 'Ücretli Kullanıma Geçişler')
@section('content')
    <h1 class="text-2xl font-bold text-slate-900">Ücretli Kullanıma Geçişler</h1>
    <p class="mt-1 text-sm text-slate-500">Dönem seçiminden aboneliğin etkinleşmesine kadar işlem geçmişi.</p>
    <form method="GET" action="{{ route('admin.checkout-journeys.index') }}" class="my-5 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4">
        @if ($customerId > 0)<input type="hidden" name="customer" value="{{ $customerId }}">@endif
        <input name="search" value="{{ $search }}" aria-label="Geçiş ara" placeholder="Takip no, müşteri, apartman veya sipariş" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-80">
        <select name="status" aria-label="Süreç durumu" class="max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Durum: Tümü</option>
            @foreach (['incomplete' => 'Tamamlanmadı', 'closed' => 'Ekrandan çıkıldı', 'pending' => 'Ödeme bekleniyor', 'active' => 'Etkinleşti', 'review' => 'İnceleme gerekli'] as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="method" aria-label="Ödeme yöntemi" class="max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Yöntem: Tümü</option><option value="kredi_kartı" @selected($method === 'kredi_kartı')>Kredi kartı</option><option value="havale" @selected($method === 'havale')>Havale / EFT</option>
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ara</button>
        @if ($search !== '' || $status !== '' || $method !== '' || $customerId > 0)<a href="{{ route('admin.checkout-journeys.index') }}" class="self-center text-sm font-semibold text-slate-600">Temizle</a>@endif
    </form>
    <div class="space-y-3">
        @forelse ($journeys as $journey)
            <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1 break-words">
                        <a href="{{ route('admin.checkout-journeys.show', $journey) }}" class="font-mono font-semibold text-emerald-700">{{ $journey->reference }}</a>
                        <p class="mt-1 font-medium text-slate-900">{{ $journey->user?->name ?? 'Silinmiş müşteri' }} · {{ $journey->apartment?->name ?? 'Apartman bilgisi yok' }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ match ($journey->period) { 'monthly' => 'Aylık', 'yearly' => 'Yıllık', default => 'Dönem seçilmedi' } }} · {{ match ($journey->payment_method) { 'kredi_kartı' => 'Kredi kartı', 'havale' => 'Havale / EFT', default => 'Yöntem seçilmedi' } }}@if ($journey->quoted_amount !== null) · {{ number_format((float) $journey->quoted_amount, 2, ',', '.') }} ₺@endif</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $journey->order?->order_number ?? 'Sipariş oluşmadı' }} · Başlangıç: {{ $journey->created_at->format('d.m.Y H:i') }}</p>
                    </div>
                    <div class="shrink-0 text-sm sm:text-right">
                        <p class="font-semibold text-slate-800">{{ $journey->progress_label }}</p>
                        <p class="mt-1 text-slate-500">Son aşama: {{ $journey->stage_label }}</p>
                        <p class="text-xs text-slate-400">{{ $journey->last_seen_at->format('d.m.Y H:i') }}</p>
                        <a href="{{ route('admin.checkout-journeys.show', $journey) }}" class="mt-2 inline-block font-semibold text-emerald-700">Geçmişi gör</a>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">Geçiş süreci bulunamadı.</p>
        @endforelse
    </div>
    <p class="mt-4 text-xs text-slate-500">30 dakika boyunca yeni adım kaydedilmeyen ve ödemeye gönderilmeyen süreçler “Tamamlanmadı” görünür. Bu, tarayıcının kesin olarak kapatıldığı anlamına gelmez.</p>
    <div class="mt-5">{{ $journeys->links() }}</div>
    <a href="{{ route('admin.card-transactions.index') }}" class="mt-4 inline-block text-sm font-semibold text-slate-600">Ödeme denemelerini ayrıca listele</a>
@endsection
