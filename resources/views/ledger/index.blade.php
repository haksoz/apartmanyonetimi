@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Muhasebe Hareketleri</h1>
            <p class="mt-1 text-sm text-slate-500">Tahsilatları, ödemeleri ve borçlandırmaları tek yerden takip edin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('accounts.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Hesaplar</a>
        </div>
    </div>

    @include('ledger._filters')

    <p class="mb-4 text-sm text-slate-500">{{ $transactions->total() }} hareket bulundu. Tahsilat ve ödemelerde kategori, tahsis edilen gider veya borcun kategorisidir.</p>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white shadow-sm">
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
                        $isPayment = $related instanceof \App\Models\Payment;
                        $isOrphan = $isPayment && (! $related->account_id || $transaction->account?->is_hidden);
                        $relatedLabel = match (true) {
                            $isPayment => ($transaction->type === 'credit' ? 'Tahsilat' : 'Ödeme').' #'.$related->id,
                            $related instanceof \App\Models\Due => 'Borç #'.$related->id,
                            $related instanceof \App\Models\Expense => 'Gider #'.$related->id,
                            default => '-',
                        };
                        $relatedUrl = null;
                        if ($related && $related instanceof \App\Models\Payment) {
                            $relatedUrl = route('payments.show', $related);
                        }
                        if ($related && $related instanceof \App\Models\Due) {
                            $relatedUrl = route('dues.show', $related);
                        }
                        if ($related && $related instanceof \App\Models\Expense) {
                            $relatedUrl = route('expenses.show', $related);
                        }
                    @endphp
                    <tr>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $isOrphan ? 'Hesapsız' : ($transaction->account?->name ?? '-') }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->description ?: ucfirst($transaction->type) }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $transaction->type === 'debit' ? 'Borç' : 'Alacak' }}</td>
                        <td class="px-5 py-4 text-right font-semibold {{ $transaction->type === 'debit' ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format($transaction->amount, 2, ',', '.') }} TL
                            @include('ledger._allocation')
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            @if ($relatedUrl)
                                <a href="{{ $relatedUrl }}" class="underline hover:text-slate-700">{{ $relatedLabel }}</a>
                            @else
                                {{ $relatedLabel }}
                            @endif
                            @include('ledger._cash-links')
                            @if($isPayment && $related->unallocated_amount > 0)
                                <a href="{{ $transaction->account?->type === \App\Models\Account::TYPE_SUPPLIER ? route('payments.supplier-allocations.create', $related) : route('payments.allocations.create', $related) }}" class="mt-2 block text-xs font-semibold text-emerald-700 hover:underline">Tahsis Et</a>
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
                $isPayment = $related instanceof \App\Models\Payment;
                $isOrphan = $isPayment && (! $related->account_id || $transaction->account?->is_hidden);
                $relatedLabel = match (true) {
                    $isPayment => ($transaction->type === 'credit' ? 'Tahsilat' : 'Ödeme').' #'.$related->id,
                    $related instanceof \App\Models\Due => 'Borç #'.$related->id,
                    $related instanceof \App\Models\Expense => 'Gider #'.$related->id,
                    default => '-',
                };
                $relatedUrl = null;
                if ($related && $related instanceof \App\Models\Payment) {
                    $relatedUrl = route('payments.show', $related);
                }
                if ($related && $related instanceof \App\Models\Due) {
                    $relatedUrl = route('dues.show', $related);
                }
                if ($related && $related instanceof \App\Models\Expense) {
                    $relatedUrl = route('expenses.show', $related);
                }
            @endphp
            @include('ledger._mobile-card')
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz muhasebe hareketi bulunamadı.
            </div>
        @endforelse
    </div>

    {{-- Sayfalama --}}
    @if ($transactions->hasPages())
        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
    @endif
@endsection
