@extends('layouts.report-pdf')

@section('title', 'Yıllık Faaliyet Raporu — ' . $apartment->name)

@section('content')
    <h2>YILLIK FAALİYET RAPORU — {{ $apartment->name }} — {{ $year }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">
        Tahakkuk {{ number_format($totalDues, 2, ',', '.') }} ₺
        · Tahsil {{ number_format($collectedDues, 2, ',', '.') }} ₺
        · Kalan {{ number_format($pendingDues, 2, ',', '.') }} ₺
        · Gider {{ number_format($totalExpenses, 2, ',', '.') }} ₺
        · Kasa {{ number_format($cashIn - $cashOut, 2, ',', '.') }} ₺
    </p>

    <h3>Aylık Döküm</h3>
    <table>
        <thead>
            <tr>
                <th>Ay</th>
                <th class="text-right">Tahakkuk</th>
                <th class="text-right">Tahsilat</th>
                <th class="text-right">Gider</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthlyData as $m => $mData)
                <tr>
                    <td>{{ $monthNames[$m] }}</td>
                    <td class="text-right">{{ number_format($mData['dues'], 2, ',', '.') }} ₺</td>
                    <td class="text-right text-green">{{ number_format($mData['payments'], 2, ',', '.') }} ₺</td>
                    <td class="text-right text-red">{{ number_format($mData['expenses'], 2, ',', '.') }} ₺</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOPLAM</td>
                <td class="text-right">{{ number_format(collect($monthlyData)->sum('dues'), 2, ',', '.') }} ₺</td>
                <td class="text-right text-green">{{ number_format(collect($monthlyData)->sum('payments'), 2, ',', '.') }} ₺</td>
                <td class="text-right text-red">{{ number_format(collect($monthlyData)->sum('expenses'), 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
    </table>

    @if($expenseByCategory->count())
        <h3>Gider Kategorileri</h3>
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="text-right">Tutar</th>
                    <th class="text-right">Pay %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expenseByCategory as $cat => $amount)
                    <tr>
                        <td>{{ $cat }}</td>
                        <td class="text-right">{{ number_format($amount, 2, ',', '.') }} ₺</td>
                        <td class="text-right">{{ $totalExpenses > 0 ? number_format(($amount / $totalExpenses) * 100, 1) : '0' }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
