@extends('layouts.report-pdf')

@section('title', $basis === 'category' ? 'Kategorili Gider Raporu' : 'Açıklamalı Gider Raporu')

@section('content')
    <h2>{{ $basis === 'category' ? 'KATEGORİLİ GİDER RAPORU' : 'AÇIKLAMALI GİDER RAPORU' }} — {{ $apartment->name }}</h2>
    @if ($apartment->address)
        <p class="subtitle">{{ $apartment->address }}</p>
    @endif
    <div class="meta">{{ $rangeLabel }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>

    @if ($basis === 'category')
        <table>
            <thead>
                <tr>
                    <th>Kategori</th>
                    <th class="text-right">Tutar</th>
                    <th class="text-right">Pay %</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $item)
                    <tr>
                        <td>{{ $item['category'] }}</td>
                        <td class="text-right">{{ number_format($item['amount'], 2, ',', '.') }} ₺</td>
                        <td class="text-right">{{ $total > 0 ? number_format(($item['amount'] / $total) * 100, 1) : '0' }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Bu dönemde gider bulunamadı.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td>TOPLAM</td>
                    <td class="text-right text-red">{{ number_format($total, 2, ',', '.') }} ₺</td>
                    <td class="text-right">100%</td>
                </tr>
            </tfoot>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>Açıklama</th>
                    <th class="text-right">Tutar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $item)
                    <tr>
                        <td>{{ $item['date'] }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td class="text-right">{{ number_format($item['amount'], 2, ',', '.') }} ₺</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Bu dönemde gider bulunamadı.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">TOPLAM</td>
                    <td class="text-right text-red">{{ number_format($total, 2, ',', '.') }} ₺</td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
