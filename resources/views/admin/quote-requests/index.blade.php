@extends('layouts.app')

@section('title', 'Teklif Talepleri')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Teklif Talepleri</h1>
        <p class="mt-1 text-sm text-slate-500">101 ve üzeri daireli apartmanlar için alınan talepler.</p>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tarih</th>
                        <th class="px-4 py-3 font-semibold">Kullanıcı</th>
                        <th class="px-4 py-3 font-semibold">Apartman adı</th>
                        <th class="px-4 py-3 font-semibold">Daire sayısı</th>
                        <th class="px-4 py-3 font-semibold">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($requests as $quoteRequest)
                        <tr>
                            <td class="px-4 py-3 text-slate-700">{{ $quoteRequest->created_at?->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ $quoteRequest->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-900">{{ $quoteRequest->apartment_name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $quoteRequest->unit_count }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.quote-requests.update', $quoteRequest) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        @foreach (\App\Models\QuoteRequest::STATUSES as $status)
                                            <option value="{{ $status }}" @selected($quoteRequest->status === $status)>{{ \App\Models\QuoteRequest::STATUS_LABELS[$status] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Kaydet</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-slate-500">Henüz teklif talebi yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection
