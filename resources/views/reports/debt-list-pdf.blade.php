@extends('layouts.report-pdf')

@section('title', ($tableTitle ?? 'Borç Listesi') . ' — ' . $apartment->name)

@section('content')
    <h2>{{ $tableTitle ?? 'Borç Listesi' }} — {{ $apartment->name }}</h2>
    <div class="meta">{{ $apartment->name }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Daire</th>
                <th style="width: 20%;">Hesap Adı</th>
                <th style="width: 55%;">Detaylar</th>
                <th style="width: 15%;" class="text-right">Toplam Borç (₺)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>{{ $group->unit?->unit_no ?? '-' }}</td>
                    <td>{{ $group->account?->name ?? '-' }}</td>
                    <td>
                        @foreach($group->dues as $due)
                            <span class="detail-row">
                                {{ $due->created_at_manual?->format('d.m.Y') ?? $due->created_at?->format('d.m.Y') ?? '-' }}
                                | {{ number_format($due->amount, 2, ',', '.') }} ₺
                                | {{ $due->description ?? '-' }}
                            </span>
                        @endforeach
                    </td>
                    <td class="text-right text-red">{{ number_format($group->total_remaining, 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">Borçlu hesap bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($groups->count())
        <tfoot>
            <tr>
                <td colspan="3">TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
