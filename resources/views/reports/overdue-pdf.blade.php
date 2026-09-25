@extends('layouts.report-pdf')

@section('title', 'Gecikme Raporu — ' . $apartment->name)

@section('content')
    <h2>GECİKME RAPORU — {{ $apartment->name }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">{{ $dues->count() }} kayıt · Toplam {{ number_format($totalOverdue, 2, ',', '.') }} ₺ · Ortalama {{ $avgDays }} gün</p>

    <table>
        <thead>
            <tr>
                <th>Daire</th>
                <th>Hesap Adı</th>
                <th>Kategori</th>
                <th>Açıklama</th>
                <th>Vade Tarihi</th>
                <th class="text-center">Gecikme (Gün)</th>
                <th class="text-right">Toplam</th>
                <th class="text-right">Kalan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dues as $due)
                <tr>
                    <td>{{ $due->unit?->unit_no ?? '-' }}</td>
                    <td>{{ $due->account?->name ?? '-' }}</td>
                    <td>{{ $due->category?->name ?? '-' }}</td>
                    <td>{{ $due->description ?? '-' }}</td>
                    <td>{{ $due->due_date?->format('d.m.Y') ?? '-' }}</td>
                    <td class="text-center">{{ $due->days_overdue }} gün</td>
                    <td class="text-right">{{ number_format($due->amount, 2, ',', '.') }} ₺</td>
                    <td class="text-right text-red">{{ number_format($due->remaining_amount, 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted">Gecikmiş aidat bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($dues->count())
        <tfoot>
            <tr>
                <td colspan="7">TOPLAM GECİKMİŞ</td>
                <td class="text-right text-red">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
