@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Muhasebe Hareketleri</h1>
            <p class="mt-1 text-sm text-slate-500">Hesap bazlı hareketleri, belgelere bağlantıları ve tutarları görüntüleyin.</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaplar</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3">Tarih</th>
                    <th class="px-5 py-3">Hesap</th>
                    <th class="px-5 py-3">Açıklama</th>
                    <th class="px-5 py-3">Tip</th>
                    <th class="px-5 py-3 text-right">Tutar</th>
                    <th class="px-5 py-3">Belge</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $transaction)
                    @php
                        $related = $transaction->transactionable;
                        $relatedLabel = $related ? class_basename($transaction->transactionable_type) . ' #' . $transaction->transactionable_id : '-';
                        $relatedUrl = null;
                        if ($related && class_basename($transaction->transactionable_type) === 'Payment') {
                            $relatedUrl = route('payments.show', $related);
                        }
                        if ($related && class_basename($transaction->transactionable_type) === 'Due') {
                            $relatedUrl = route('dues.show', $related);
                        }
                    @endphp
                    <tr>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->account?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->description ?: ucfirst($transaction->type) }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ ucfirst($transaction->type) }}</td>
                        <td class="px-5 py-4 text-right font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-slate-700">
                            @if ($relatedUrl)
                                <a href="{{ $relatedUrl }}" class="underline hover:text-slate-700">{{ $relatedLabel }}</a>
                            @else
                                {{ $relatedLabel }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-6 text-sm text-slate-500">Henüz muhasebe hareketi bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
