@extends('layouts.report-pdf')

@section('title', 'Alacak Listesi — ' . $apartment->name)

@section('content')
    <h2>ALACAK LİSTESİ — {{ $apartment->name }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>

    <table>
        <thead>
            <tr>
                <th>Hesap Adı</th>
                <th>Daire</th>
                <th>Tür</th>
                <th class="text-right">Toplam Ödeme</th>
                <th class="text-right">Alacak</th>
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->name }}</td>
                    <td>{{ $account->unit?->unit_no ?? '-' }}</td>
                    <td>{{ $account->type_label }}</td>
                    <td class="text-right">{{ number_format($account->total_payments, 2, ',', '.') }} ₺</td>
                    <td class="text-right">{{ number_format($account->total_receivable, 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted">Alacak kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($accounts->count())
        <tfoot>
            <tr>
                <td colspan="4">TOPLAM ALACAK</td>
                <td class="text-right">{{ number_format($accounts->sum('total_receivable'), 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
