@extends('layouts.report-pdf')

@section('title', $title ?? 'Aylık Aidat Pano Tablosu')

@section('content')
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
    <h2>{{ $title ?? 'Aylık Aidat Tablosu' }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>

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
                @php $data = $accountData[$account->id]; @endphp
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
@endsection
