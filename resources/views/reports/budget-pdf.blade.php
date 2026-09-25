@extends('layouts.report-pdf')

@section('title', 'Bütçe Raporu — ' . $apartment->name)

@section('content')
    <h2>BÜTÇE RAPORU — {{ $apartment->name }} — {{ $year }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">Bu rapor gerçekleşen giderleri gösterir. Toplam {{ number_format($totalActual, 2, ',', '.') }} ₺ · {{ $rows->count() }} kategori</p>

    <table>
        <thead>
            <tr>
                <th>Kategori</th>
                <th class="text-right">Gerçekleşen (₺)</th>
                <th class="text-right">Pay %</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php $pct = $totalActual > 0 ? ($row['actual'] / $totalActual) * 100 : 0; @endphp
                <tr>
                    <td>{{ $row['category'] }}</td>
                    <td class="text-right">{{ number_format($row['actual'], 2, ',', '.') }} ₺</td>
                    <td class="text-right">{{ number_format($pct, 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">Bu yıl için gider kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($rows->count())
        <tfoot>
            <tr>
                <td>TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalActual, 2, ',', '.') }} ₺</td>
                <td class="text-right">100%</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
