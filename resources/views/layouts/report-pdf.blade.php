<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Rapor')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #334155; margin: 10mm; }
        h2 { font-size: 13px; margin: 0 0 6px; color: #334155; }
        h3 { font-size: 12px; margin: 14px 0 6px; color: #334155; }
        .meta { font-size: 9px; color: #94a3b8; margin-bottom: 10px; text-align: right; }
        .subtitle { font-size: 10px; color: #64748b; margin: 0 0 2px; }
        .note { font-size: 9px; color: #64748b; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
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
        .summary { font-size: 11px; font-weight: bold; padding: 6px 10px; margin-bottom: 10px; }
        .summary-debt { background: #fee2e2; color: #b91c1c; }
        .summary-credit { background: #d1fae5; color: #065f46; }
        .summary-zero { background: #f8fafc; color: #475569; }
        .detail-row { display: block; margin-bottom: 2px; }
        .detail-row:last-child { margin-bottom: 0; }
        .row-opening { background: #f8fafc; }
        table.compact th, table.compact td { padding: 3px 2px; font-size: 8px; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
