@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Muhasebe Hareketleri</h1>
            <p class="mt-1 text-sm text-slate-500">Hesap bazlı hareketleri, belgelere bağlantıları ve tutarları görüntüleyin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('accounts.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Hesaplar</a>
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3">Tarih</th>
                    <th class="px-5 py-3">Hesap</th>
                    <th class="px-5 py-3">Açıklama</th>
                    <th class="px-5 py-3">Tip</th>
                    <th class="px-5 py-3 text-right">Tutar</th>
                    <th class="px-5 py-3">Belge</th>
                    <th class="px-5 py-3">Referans ID</th>
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
                        if ($related && class_basename($transaction->transactionable_type) === 'Expense') {
                            $relatedUrl = route('expenses.show', $related);
                        }
                    @endphp
                    <tr>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->account?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->description ?: ucfirst($transaction->type) }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->type === 'debit' ? 'Borç' : 'Alacak' }}</td>
                        <td class="px-5 py-4 text-right font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-slate-700">
                            @if ($relatedUrl)
                                <a href="{{ $relatedUrl }}" class="underline hover:text-slate-700">{{ $relatedLabel }}</a>
                            @else
                                {{ $relatedLabel }}
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-700 text-xs">
                            @if ($related && $related->reference_number)
                                {{ $related->reference_number }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-6 text-sm text-slate-500">Henüz muhasebe hareketi bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
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
                if ($related && class_basename($transaction->transactionable_type) === 'Expense') {
                    $relatedUrl = route('expenses.show', $related);
                }
            @endphp
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                {{-- Header: Date & Type Badge --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-lg font-bold text-slate-900">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</div>
                        <div class="text-sm text-slate-600">{{ $transaction->account?->name ?? '-' }}</div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold {{ $transaction->type === 'debit' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $transaction->type === 'debit' ? 'bg-red-600' : 'bg-emerald-600' }}"></span>
                        {{ $transaction->type === 'debit' ? 'Borç' : 'Alacak' }}
                    </span>
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-1 gap-3 mb-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                        <div class="font-medium text-slate-900">{{ $transaction->description ?: ucfirst($transaction->type) }}</div>
                    </div>
                    @if ($relatedUrl)
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Belge</div>
                            <a href="{{ $relatedUrl }}" class="font-medium text-slate-900 underline hover:text-slate-700">{{ $relatedLabel }}</a>
                        </div>
                    @elseif ($relatedLabel !== '-')
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Belge</div>
                            <div class="font-medium text-slate-900">{{ $relatedLabel }}</div>
                        </div>
                    @endif
                    @if ($related && $related->reference_number)
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Referans ID</div>
                            <div class="font-medium text-slate-900">{{ $related->reference_number }}</div>
                        </div>
                    @endif
                </div>

                {{-- Amount Section --}}
                <div class="bg-slate-50 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-500">Tutar</div>
                        <div class="text-lg font-bold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz muhasebe hareketi bulunamadı.
            </div>
        @endforelse
    </div>
@endsection
