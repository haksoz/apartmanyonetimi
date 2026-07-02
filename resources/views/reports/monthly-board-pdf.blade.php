<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title : 'Aylık Aidat Pano Tablosu' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #334155; margin: 10mm; }
        h2 { font-size: 13px; margin: 0 0 6px; color: #334155; }
        .meta { font-size: 9px; color: #94a3b8; margin-bottom: 10px; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; }
        th { background: #f1f5f9; color: #475569; font-size: 10px; font-weight: 600; text-transform: uppercase; }
        td { vertical-align: top; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        tfoot td { background: #f8fafc; font-weight: 600; }
        .text-red { color: #ef4444; }
        .text-green { color: #10b981; }
        .text-muted { color: #94a3b8; }
        .text-small { font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $trMonthsH = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
    @endphp
    <h2>{{ isset($title) ? $title : 'Aylık Aidat Tablosu' }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>

    @php
        $totalBorç = 0;
        $totalÖdenen = 0;
        $pastRemainingAll = 0;
        $selectedAmountAll = 0;
        $remainingAll = 0;
        foreach($accounts as $account) {
            $data = $accountData[$account->id];
            $totalBorç   += $data['selectedAmount'];
            $totalÖdenen += $data['paid'];
            $pastRemainingAll += $data['pastRemaining'];
            $selectedAmountAll += $data['selectedAmount'];
            $remainingAll += $data['remaining'];
        }
    @endphp

    <table>
        <thead>
            <tr>
                <th>Daire No</th>
                <th>Hesap Adı</th>
                <th class="text-right">Geçmiş Borç (₺)</th>
                <th class="text-right">{{ $trMonthsH[$parsedMonth->month] }} Borç (₺)</th>
                <th class="text-right">Ödenen (₺)</th>
                <th class="text-right">Kalan (₺)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                @php
                    $data = $accountData[$account->id];
                @endphp
                <tr>
                    <td>{{ $account->unit?->unit_no }}</td>
                    <td>{{ $account->name }}{{ $showAccountType && $account->type === 'owner' ? ' (Kat Maliki)' : ($showAccountType && $account->type === 'tenant' ? ' (Kiracı)' : '') }}</td>
                    <td class="text-right">{{ $data['pastRemaining'] > 0 ? number_format($data['pastRemaining'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    <td class="text-right">{{ $data['selectedAmount'] > 0 ? number_format($data['selectedAmount'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    <td class="text-right text-green">{{ $data['paid'] > 0 ? number_format($data['paid'], 2, ',', '.') . ' ₺' : '—' }}</td>
                    <td class="text-right {{ $data['remaining'] > 0 ? 'text-red' : 'text-muted' }}">
                        {{ $data['remaining'] > 0 ? number_format($data['remaining'], 2, ',', '.') . ' ₺' : '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Hesap kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">TOPLAM</td>
                <td class="text-right">{{ $pastRemainingAll > 0 ? number_format($pastRemainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right">{{ $selectedAmountAll > 0 ? number_format($selectedAmountAll, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right text-green">{{ $totalÖdenen > 0 ? number_format($totalÖdenen, 2, ',', '.') . ' ₺' : '—' }}</td>
                <td class="text-right text-red">{{ $remainingAll > 0 ? number_format($remainingAll, 2, ',', '.') . ' ₺' : '—' }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
