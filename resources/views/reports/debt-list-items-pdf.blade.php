@extends('layouts.report-pdf')

@section('title', 'Borç Listesi — ' . $apartment->name)

@section('content')
    <h2>BORÇ LİSTESİ — {{ $apartment->name }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>

    <table>
        <thead>
            <tr>
                <th>Daire</th>
                <th>Hesap Adı</th>
                <th>Kategori</th>
                <th>Dönem</th>
                <th class="text-right">Toplam</th>
                <th class="text-right">Kalan</th>
                <th>Vade</th>
                <th class="text-center">Durum</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dues as $due)
                <tr>
                    <td>{{ $due->unit?->unit_no ?? '-' }}</td>
                    <td>{{ $due->account?->name ?? '-' }}</td>
                    <td>{{ $due->category?->name ?? '-' }}</td>
                    <td>{{ $due->period ?? '-' }}</td>
                    <td class="text-right">{{ number_format($due->amount, 2, ',', '.') }} ₺</td>
                    <td class="text-right text-red">{{ number_format($due->remaining_amount, 2, ',', '.') }} ₺</td>
                    <td>{{ $due->due_date?->format('d.m.Y') ?? '-' }}</td>
                    <td class="text-center">
                        @if($due->due_date && $due->due_date->isPast())
                            Gecikmiş
                        @elseif($due->remaining_amount < $due->amount)
                            Kısmi
                        @else
                            Bekliyor
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted">Borç kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($dues->count())
        <tfoot>
            <tr>
                <td colspan="5">TOPLAM</td>
                <td class="text-right text-red">{{ number_format($total, 2, ',', '.') }} ₺</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
