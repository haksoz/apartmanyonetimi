@extends('layouts.report-pdf')

@section('title', $title ?? 'Aylık Borç Tablosu')

@section('content')
    <style>
        .monthly-board-title {
            color: #020617;
            font-weight: 800;
        }
        .monthly-board-note {
            color: #1e293b;
            font-weight: 600;
        }
        .monthly-board-table {
            color: #111827;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .monthly-board-table th {
            background: #dbeafe;
            border-color: #475569;
            color: #0f172a;
            font-weight: 800;
        }
        .monthly-board-table td {
            border-color: #64748b;
            color: #111827;
            font-weight: 500;
        }
        .monthly-board-table tfoot td {
            background: #e2e8f0;
            color: #020617;
            font-weight: 800;
        }
        .monthly-board-table .text-green {
            color: #047857;
            font-weight: 700;
        }
        .monthly-board-table .text-red {
            color: #b91c1c;
            font-weight: 700;
        }
        .monthly-board-table .text-muted {
            color: #334155;
        }
        .monthly-board-amount {
            display: block;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .monthly-board-status {
            display: block;
            margin-top: 3px;
            font-size: 9px;
            line-height: 1.2;
            white-space: nowrap;
        }
        .monthly-board-total {
            display: inline-block;
            padding: 3px 5px;
            border: 1px solid #fecaca;
            border-radius: 5px;
            background: #fef2f2;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .monthly-board-total-zero {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
    </style>
    @php
        $totalBorç = 0;
        $totalÖdenen = 0;
        $pastRemainingAll = 0;
        $selectedAmountAll = 0;
        $remainingAll = 0;
        foreach ($accounts as $account) {
            $data = $accountData[$account->id];
            $totalBorç   += $data['selectedAmount'];
            $totalÖdenen += $data['paid'];
            $pastRemainingAll += $data['pastRemaining'];
            $selectedAmountAll += $data['selectedAmount'];
            $remainingAll += $data['remaining'];
        }
    @endphp
    <h2 class="monthly-board-title">{{ $title }}</h2>

    @if ($unknownDuesCount > 0)
        <p class="note monthly-board-note">Borç türü belirlenmemiş {{ $unknownDuesCount }} borç bu tabloya dahil edilmedi.</p>
    @endif

    <table class="monthly-board-table">
        <thead>
            <tr>
                <th>Daire No</th>
                @unless($hideAccountNames)
                    <th>Hesap Adı</th>
                @endunless
                <th class="text-right">Geçmiş Borç (₺)</th>
                @foreach($reportMonths as $key)
                    <th class="px-4 py-3 text-right font-semibold">{{ $trMonths[(int) substr($key, 5, 2)] }} {{ substr($key, 0, 4) }}</th>
                @endforeach
                <th class="text-right">TOPLAM<br>BORÇ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                @php $data = $accountData[$account->id]; @endphp
                <tr>
                    <td>{{ $account->unit?->unit_no }}@unless($hideAccountType) ({{ $account->type_label }})@endunless</td>
                    @unless($hideAccountNames)
                        <td>{{ $account->name }}</td>
                    @endunless
                    <td class="text-right">{{ $data['pastRemaining'] > 0 ? number_format($data['pastRemaining'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    @foreach($reportMonths as $key)
                        @php $cell = $monthlyData[$account->id][$key]; @endphp
                        <td class="text-right">
                            @if($cell['amount'] > 0)
                                <span class="monthly-board-amount">{{ number_format($cell['amount'], 2, ',', '.') }} ₺</span>
                                <span class="monthly-board-status {{ $cell['remaining'] <= 0 ? 'text-green' : ($cell['paid'] > 0 ? '' : 'text-red') }}">({{ $cell['status'] }})</span>
                            @else — @endif
                        </td>
                    @endforeach
                    <td class="text-right {{ $data['remaining'] > 0 ? 'text-red' : 'text-muted' }}">
                        <span class="monthly-board-total {{ $data['remaining'] > 0 ? '' : 'monthly-board-total-zero' }}">{{ $data['remaining'] > 0 ? number_format($data['remaining'], 2, ',', '.') . ' ₺' : '—' }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($reportMonths) + ($hideAccountNames ? 3 : 4) }}" class="text-center text-muted">Hesap kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $hideAccountNames ? 1 : 2 }}">TOPLAM</td>
                <td class="text-right">{{ $pastRemainingAll > 0 ? number_format($pastRemainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                @foreach($reportMonths as $key)
                    @php $monthTotal = array_sum(array_map(fn ($item) => $item[$key]['amount'], $monthlyData)); @endphp
                    <td class="px-4 py-3 text-right font-bold">{{ $monthTotal > 0 ? number_format($monthTotal, 2, ',', '.').' ₺' : '—' }}</td>
                @endforeach
                <td class="text-right text-red"><span class="monthly-board-total">{{ $remainingAll > 0 ? number_format($remainingAll, 2, ',', '.') . ' ₺' : '—' }}</span></td>
            </tr>
        </tfoot>
    </table>
@endsection
