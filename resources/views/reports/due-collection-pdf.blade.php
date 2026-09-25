@extends('layouts.report-pdf')

@section('title', 'Aidat Tahsilat Raporu — ' . $apartment->name)

@section('content')
    <h2>AİDAT TAHSİLAT RAPORU — {{ $apartment->name }} — {{ $year }}</h2>
    <div class="meta">{{ now()->format('d.m.Y') }} tarihli çıktı</div>
    <p class="note">Ödendi · Kısmi · Gecikmeli · Bekliyor · Yok</p>

    <table class="compact">
        <thead>
            <tr>
                <th>Daire / Hesap</th>
                @foreach($monthNames as $mn)
                    <th class="text-center">{{ $mn }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($accounts as $account)
                <tr>
                    <td>
                        {{ $account->unit?->unit_no }} - {{ $account->name }}
                        <div class="text-small">{{ $account->type_label }}</div>
                    </td>
                    @foreach($months as $m)
                        @php $status = $matrix[$account->id][$m] ?? null; @endphp
                        <td class="text-center">
                            @if($status === 'paid')
                                <span class="text-green">Ödendi</span>
                            @elseif($status === 'partial')
                                Kısmi
                            @elseif($status === 'overdue')
                                <span class="text-red">Gecikmeli</span>
                            @elseif($status === 'pending')
                                Bekliyor
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="13" class="text-center text-muted">Daire kaydı bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
