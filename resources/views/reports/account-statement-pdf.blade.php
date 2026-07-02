<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Cari Ekstre — {{ $account?->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #334155; margin: 10mm; }
        h2 { font-size: 13px; margin: 0 0 2px; color: #1e293b; }
        .subtitle { font-size: 10px; color: #64748b; margin: 0 0 2px; }
        .meta { font-size: 9px; color: #94a3b8; margin-bottom: 8px; text-align: right; }
        .summary { font-size: 11px; font-weight: bold; padding: 6px 10px; border-radius: 5px; margin-bottom: 10px; }
        .summary-debt   { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .summary-credit { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .summary-zero   { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; color: #475569; font-size: 9px; font-weight: 600; text-transform: uppercase; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .text-red   { color: #ef4444; }
        .text-green { color: #10b981; }
        .text-muted { color: #94a3b8; }
        .row-opening { background: #f8fafc; }
        tfoot td { background: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>
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
                        @if($openingBalance != 0)<span style="font-size:8px">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>@endif
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
                        @if($tx->running_balance != 0)<span style="font-size:8px">{{ $tx->running_balance < 0 ? 'B' : 'A' }}</span>@endif
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
                    @if($runningBalance != 0)<span style="font-size:8px">{{ $runningBalance < 0 ? 'B' : 'A' }}</span>@endif
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
