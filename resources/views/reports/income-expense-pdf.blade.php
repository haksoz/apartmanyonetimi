@extends('layouts.report-pdf')

@section('title', 'Gelir-Gider Raporu — ' . $apartment->name)

@section('content')
    <h2>GELİR-GİDER RAPORU — {{ $apartment->name }}</h2>
    <p class="note">Gider İlişkisi: {{ $expenseRelationLabel }}</p>
    <div class="meta">{{ $rangeLabel }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">{{ $expenseRelation === 'all' ? 'Gelir, tedarikçi ödemeleri hariç tüm ödemelerin toplamıdır.' : ($expenseRelation === 'unassigned' ? 'Gelir: borç türü belirlenmemiş tutarlar. Gider: ilişkisi henüz belirlenmemiş kayıtlar.' : 'Gelir: seçilen borç türüne bağlanan tutarlar. Gider: seçilen gider ilişkisine ait kayıtlar.') }}</p>
    <p class="note">Devir {{ number_format($openingBalance, 2, ',', '.') }} ₺ · Dönem sonu {{ number_format($closingBalance, 2, ',', '.') }} ₺ · Aidat {{ number_format($aidatIncome, 2, ',', '.') }} ₺ · Kat Maliki Payı {{ number_format($ownerShareIncome, 2, ',', '.') }} ₺ · Diğer {{ number_format($otherIncome, 2, ',', '.') }} ₺</p>

    <table>
        <thead>
            <tr>
                <th>Dönem</th>
                <th class="text-right">Gider</th>
                <th class="text-right">Gelir</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="text-right text-red">{{ number_format($row['expense'], 2, ',', '.') }} ₺</td>
                    <td class="text-right text-green">{{ number_format($row['income'], 2, ',', '.') }} ₺</td>
                    <td class="text-right {{ $row['net'] >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($row['net'], 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Bu dönemde kayıt bulunamadı.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalExpense, 2, ',', '.') }} ₺</td>
                <td class="text-right text-green">{{ number_format($totalIncome, 2, ',', '.') }} ₺</td>
                <td class="text-right {{ $totalNet >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($totalNet, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
    </table>

    @include('reports._income-expense-history')
@endsection
