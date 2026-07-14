<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tableTitle ?? 'Borç Listesi' }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #334155; margin: 10mm; }
        h2 { font-size: 13px; margin: 0 0 4px; color: #334155; }
        .meta { font-size: 9px; color: #94a3b8; margin-bottom: 10px; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; }
        th { background: #f1f5f9; color: #475569; font-size: 9px; font-weight: 600; text-transform: uppercase; }
        td { vertical-align: top; }
        td.text-right { text-align: right; }
        td.text-red { color: #ef4444; font-weight: 600; }
        tfoot td { background: #f8fafc; font-weight: 600; }
        .detail-row { display: block; margin-bottom: 2px; }
        .detail-row:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
    <h2>{{ $tableTitle ?? 'Borç Listesi' }}</h2>
    <div class="meta">{{ $apartment->name }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Daire</th>
                <th style="width: 20%;">Hesap Adı</th>
                <th style="width: 55%;">Detaylar</th>
                <th style="width: 15%;" class="text-right">Toplam Borç (₺)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>{{ $group->unit?->unit_no ?? '-' }}</td>
                    <td>{{ $group->account?->name ?? '-' }}</td>
                    <td>
                        @foreach($group->dues as $due)
                            <span class="detail-row">
                                {{ $due->created_at_manual?->format('d.m.Y') ?? $due->created_at?->format('d.m.Y') ?? '-' }}
                                | {{ number_format($due->amount, 2, ',', '.') }} ₺
                                | {{ $due->description ?? '-' }}
                            </span>
                        @endforeach
                    </td>
                    <td class="text-right text-red">{{ number_format($group->total_remaining, 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center">Borçlu hesap bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($groups->count())
        <tfoot>
            <tr>
                <td colspan="3">TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
        @endif
    </table>
</body>
</html>
