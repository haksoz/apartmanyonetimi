<div class="mb-4 rounded-2xl border p-4 {{ $closingBalance > 0 ? 'border-red-200 bg-red-50' : ($closingBalance < 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50') }}">
    <p class="text-center text-base font-medium {{ $closingBalance > 0 ? 'text-red-700' : ($closingBalance < 0 ? 'text-emerald-700' : 'text-slate-700') }}">
        Hesabın Toplam <span class="mx-1 text-lg font-bold">{{ number_format(abs($closingBalance), 2, ',', '.') }} TL</span>
        {{ $closingBalance > 0 ? 'borcu vardır' : ($closingBalance < 0 ? 'alacağı vardır' : 'bakiyesi sıfırdır') }}
    </p>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <h2 class="text-base font-semibold text-slate-950">Hareketler</h2>
        <span class="text-sm text-slate-500">{{ $transactions->total() }} kayıt</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Tarih</th>
                    <th class="px-4 py-3 text-left font-semibold">Açıklama</th>
                    <th class="px-4 py-3 text-right font-semibold">Borç</th>
                    <th class="px-4 py-3 text-right font-semibold">Alacak</th>
                    <th class="px-4 py-3 text-right font-semibold">Bakiye</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @if($dateFrom && $transactions->onFirstPage())
                    <tr class="bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-500">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-500">Dönem Açılış Bakiyesi</td>
                        <td class="px-4 py-3 text-right text-red-500">{{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' ₺' : '' }}</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' ₺' : '' }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $openingBalance > 0 ? 'text-red-500' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                            {{ number_format(abs($openingBalance), 2, ',', '.') }} ₺
                            @if($openingBalance != 0)<span class="text-xs font-normal">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>@endif
                        </td>
                    </tr>
                @endif

                @forelse($transactions as $tx)
                    @php
                        $detailUrl = match ($tx->transactionable_type ?? null) {
                            \App\Models\Payment::class => $tx->transactionable_id ? route('payments.show', $tx->transactionable_id) : null,
                            \App\Models\Due::class => $tx->transactionable_id ? route('dues.show', $tx->transactionable_id) : null,
                            \App\Models\Expense::class => $tx->transactionable_id ? route('expenses.show', $tx->transactionable_id) : null,
                            default => null,
                        };
                    @endphp
                    <tr class="hover:bg-slate-50 {{ $detailUrl ? 'cursor-pointer' : '' }}"
                        @if($detailUrl) onclick="window.location.href='{{ $detailUrl }}'" @endif>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $tx->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $tx->description ?: '-' }}
                            @if($tx->transactionable instanceof \App\Models\Due && $tx->transactionable->due_type)
                                <span class="ml-1 text-xs text-slate-400">· {{ $tx->transactionable->due_type->label() }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-red-500">{{ $tx->type === 'debit' ? number_format($tx->amount, 2, ',', '.') . ' ₺' : '' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right text-emerald-600">{{ $tx->type === 'credit' ? number_format($tx->amount, 2, ',', '.') . ' ₺' : '' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium {{ $tx->running_balance > 0 ? 'text-red-500' : ($tx->running_balance < 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                            {{ number_format(abs($tx->running_balance), 2, ',', '.') }} ₺
                            @if($tx->running_balance != 0)<span class="text-xs font-normal">{{ $tx->running_balance > 0 ? 'B' : 'A' }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Bu dönemde hareket bulunamadı.</td></tr>
                @endforelse

                @if($transactions->isNotEmpty())
                    <tr class="border-t-2 border-slate-300 bg-slate-100 text-sm font-bold">
                        <td colspan="2" class="px-4 py-3 text-slate-700">TOPLAM</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ number_format($totalDebit, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($totalCredit, 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right {{ $closingBalance > 0 ? 'text-red-600' : ($closingBalance < 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                            {{ number_format(abs($closingBalance), 2, ',', '.') }} ₺
                            @if($closingBalance != 0)<span class="text-xs font-normal">{{ $closingBalance > 0 ? 'B' : 'A' }}</span>@endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
