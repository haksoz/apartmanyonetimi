@extends('layouts.report-pdf')

@section('title', 'Gelir-Gider Raporu — ' . $apartment->name)

@section('content')
    <h2>GELİR-GİDER RAPORU — {{ $apartment->name }}</h2>
    <div class="meta">{{ $rangeLabel }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">Tahsilat, tedarikçi ödemeleri hariç tüm ödemelerin toplamıdır. Tahsis durumuna bakılmaksızın hesaplanır.</p>

    <table>
        <thead>
            <tr>
                <th>Dönem</th>
                <th class="text-right">Tahsilat</th>
                <th class="text-right">Gider</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="text-right text-green">{{ number_format($row['income'], 2, ',', '.') }} ₺</td>
                    <td class="text-right text-red">{{ number_format($row['expense'], 2, ',', '.') }} ₺</td>
                    <td class="text-right {{ $row['net'] >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($row['net'], 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Bu dönemde kayıt bulunamadı.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOPLAM</td>
                <td class="text-right text-green">{{ number_format($totalIncome, 2, ',', '.') }} ₺</td>
                <td class="text-right text-red">{{ number_format($totalExpense, 2, ',', '.') }} ₺</td>
                <td class="text-right {{ $totalNet >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($totalNet, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
    </table>

    @if($showCategories && $expenseByCategory->count())
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
                @foreach($expenseByCategory as $cat => $total)
                    <tr>
                        <td>{{ $cat }}</td>
                        <td class="text-right">{{ number_format($total, 2, ',', '.') }} ₺</td>
                        <td class="text-right">{{ $totalExpense > 0 ? number_format(($total / $totalExpense) * 100, 1) : '0' }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
