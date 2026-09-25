@extends('layouts.report-pdf')

@section('title', 'Cari Ekstre — ' . ($account?->name ?? ''))

@section('content')
    <h2>Cari Ekstre — {{ $account?->unit ? 'Daire ' . $account->unit->unit_no . ' — ' : '' }}{{ $account?->name }}</h2>
    <p class="subtitle">{{ $apartment->name }}</p>
    <p class="subtitle">Dönem: {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</p>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>

    @php
        $summaryClass = $runningBalance < 0 ? 'summary-debt' : ($runningBalance > 0 ? 'summary-credit' : 'summary-zero');
    @endphp
    <div class="summary {{ $summaryClass }}">{{ $summaryText }}</div>

    <table>
        <thead>
            <tr>
                <th>Tarih</th>
                <th>Tür</th>
                <th>Açıklama</th>
                <th class="text-right">Borç (TL)</th>
                <th class="text-right">Alacak (TL)</th>
                <th class="text-right">Bakiye (TL)</th>
            </tr>
        </thead>
        <tbody>
            @if($dateFrom && isset($openingBalance))
                <tr class="row-opening">
                    <td>{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</td>
                    <td>Açılış</td>
                    <td>Dönem Açılış Bakiyesi</td>
                    <td class="text-right text-red">{{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' TL' : '—' }}</td>
                    <td class="text-right text-green">{{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' TL' : '—' }}</td>
                    <td class="text-right {{ $openingBalance < 0 ? 'text-green' : ($openingBalance > 0 ? 'text-red' : 'text-muted') }}">
                        {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                        @if($openingBalance != 0)<span class="text-small">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>@endif
                    </td>
                </tr>
            @endif
            @forelse($transactions as $tx)
                <tr>
                    <td>{{ $tx->transaction_date?->format('d.m.Y') ?? '-' }}</td>
                    <td>{{ $tx->type === 'debit' ? 'Borç' : 'Alacak' }}</td>
                    <td>{{ $tx->description ?? '-' }}</td>
                    <td class="text-right text-red">{{ $tx->type === 'debit' ? number_format($tx->amount, 2, ',', '.') . ' TL' : '—' }}</td>
                    <td class="text-right text-green">{{ $tx->type === 'credit' ? number_format($tx->amount, 2, ',', '.') . ' TL' : '—' }}</td>
                    <td class="text-right {{ $tx->running_balance < 0 ? 'text-red' : ($tx->running_balance > 0 ? 'text-green' : 'text-muted') }}">
                        {{ number_format(abs($tx->running_balance), 2, ',', '.') }} TL
                        @if($tx->running_balance != 0)<span class="text-small">{{ $tx->running_balance < 0 ? 'B' : 'A' }}</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Bu dönemde hareket bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($transactions->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="3" class="text-right">TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalDebit, 2, ',', '.') }} TL</td>
                <td class="text-right text-green">{{ number_format($totalCredit, 2, ',', '.') }} TL</td>
                <td class="text-right {{ $runningBalance < 0 ? 'text-red' : ($runningBalance > 0 ? 'text-green' : 'text-muted') }}">
                    {{ number_format(abs($runningBalance), 2, ',', '.') }} TL
                    @if($runningBalance != 0)<span class="text-small">{{ $runningBalance < 0 ? 'B' : 'A' }}</span>@endif
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
