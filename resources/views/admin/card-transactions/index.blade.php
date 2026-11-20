@extends('layouts.app')
@section('title', 'Kredi Kartı İşlemleri')
@section('content')
    <h1 class="text-2xl font-bold text-slate-900">Kredi Kartı İşlemleri</h1>
    <p class="mt-1 text-sm text-slate-500">Ödeme denemeleri ve bildirim geçmişi. Ekrandan çıkılması, ödemenin iptal edildiği anlamına gelmez.</p>
    <form method="GET" action="{{ route('admin.card-transactions.index') }}" class="my-5 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <input name="search" value="{{ $search }}" aria-label="İşlem ara" placeholder="İşlem no, sipariş no veya müşteri" class="min-w-0 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:w-72">
        <select name="status" aria-label="İşlem durumu" class="max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Durum: Tümü</option>
            @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
            <option value="review" @selected($status === 'review')>İnceleme gerekli</option>
            <option value="abandoned" @selected($status === 'abandoned')>Ödeme ekranından çıkıldı</option>
        </select>
        <select name="mode" aria-label="İşlem ortamı" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Ortam: Tümü</option><option value="live" @selected($mode === 'live')>Canlı</option><option value="test" @selected($mode === 'test')>Test</option>
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ara</button>
        @if ($search !== '' || $status !== '' || $mode !== '')<a href="{{ route('admin.card-transactions.index') }}" class="self-center text-sm font-semibold text-slate-600">Temizle</a>@endif
    </form>
    <div class="space-y-3 md:hidden">
        @forelse ($attempts as $attempt)
            <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-4">
                <a href="{{ route('admin.card-transactions.show', $attempt) }}" class="break-all font-mono text-sm font-semibold text-emerald-700">{{ $attempt->merchant_oid }}</a>
                <p class="mt-1 break-words text-sm text-slate-600">{{ $attempt->subscription?->user?->name ?? '—' }} · {{ $attempt->subscription?->order_number ?? '—' }}</p>
                <p class="mt-1 text-sm">{{ number_format($attempt->amount_minor / 100, 2, ',', '.') }} {{ $attempt->currency }} · {{ $attempt->created_at->format('d.m.Y H:i') }}</p>
                <div class="mt-2 flex flex-wrap gap-2">@include('admin.card-transactions.status')</div>
                <a href="{{ route('admin.card-transactions.show', $attempt) }}" class="mt-3 inline-block text-sm font-semibold text-emerald-700">Detay · {{ $attempt->notifications_count }} bildirim</a>
            </article>
        @empty
            <p class="rounded-xl bg-white p-5 text-sm text-slate-500">Kredi kartı işlemi bulunamadı.</p>
        @endforelse
    </div>
    <div class="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">İşlem / Sipariş</th><th class="px-4 py-3">Müşteri</th><th class="px-4 py-3">Tutar</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3">Tarih</th><th class="px-4 py-3">İşlem</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($attempts as $attempt)
                    <tr>
                        <td class="px-4 py-3"><div class="break-all font-mono text-xs">{{ $attempt->merchant_oid }}</div><div class="mt-1 text-slate-500">{{ $attempt->subscription?->order_number ?? '—' }}</div></td>
                        <td class="px-4 py-3">{{ $attempt->subscription?->user?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ number_format($attempt->amount_minor / 100, 2, ',', '.') }} {{ $attempt->currency }}</td>
                        <td class="px-4 py-3"><div class="flex flex-col gap-1">@include('admin.card-transactions.status')</div></td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $attempt->created_at->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3"><a href="{{ route('admin.card-transactions.show', $attempt) }}" class="font-semibold text-emerald-700">Detay</a><div class="text-xs text-slate-500">{{ $attempt->notifications_count }} bildirim</div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-5 text-center text-slate-500">Kredi kartı işlemi bulunamadı.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $attempts->links() }}</div>
@endsection
