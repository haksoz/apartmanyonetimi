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
    </style>
    @php
        $trMonthsH = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
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
                <th class="text-right">{{ $trMonthsH[$parsedMonth->month] }} Borç (₺)</th>
                <th class="text-right">{{ \Illuminate\Support\Str::beforeLast($paidLabel, ' Ödenen') }}<br>Ödenen (₺)</th>
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
                    <td class="text-right">{{ $data['selectedAmount'] > 0 ? number_format($data['selectedAmount'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    <td class="text-right text-green">{{ $data['paid'] > 0 ? number_format($data['paid'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    <td class="text-right {{ $data['remaining'] > 0 ? 'text-red' : 'text-muted' }}">
                        {{ $data['remaining'] > 0 ? number_format($data['remaining'], 2, ',', '.') . ' ₺' : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $hideAccountNames ? 5 : 6 }}" class="text-center text-muted">Hesap kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $hideAccountNames ? 1 : 2 }}">TOPLAM</td>
                <td class="text-right">{{ $pastRemainingAll > 0 ? number_format($pastRemainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right">{{ $selectedAmountAll > 0 ? number_format($selectedAmountAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right text-green">{{ $totalÖdenen > 0 ? number_format($totalÖdenen, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right text-red">{{ $remainingAll > 0 ? number_format($remainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
