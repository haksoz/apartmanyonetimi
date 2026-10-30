@extends('layouts.report-pdf')

@section('title', ($tableTitle ?? 'Borç Listesi') . ' — ' . $apartment->name)

@section('content')
    <h2>{{ $tableTitle ?? 'Borç Listesi' }} — {{ $apartment->name }}</h2>
    <div class="meta">{{ $apartment->name }} — {{ now()->format('d.m.Y') }} tarihli çıktı</div>

    <table>
        <thead>
            <tr>
                <th style="width: {{ $hideAccountNames ? '12%' : '10%' }};">Daire</th>
                @unless($hideAccountNames)
                    <th style="width: 20%;">Hesap Adı</th>
                @endunless
                <th style="width: {{ $hideAccountNames ? '70%' : '55%' }};">Detaylar</th>
                <th style="width: {{ $hideAccountNames ? '18%' : '15%' }};" class="text-right">Toplam Borç (₺)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groups as $group)
                <tr>
                    <td>{{ $group->unit?->unit_no ?? '-' }}</td>
                    @unless($hideAccountNames)
                        <td>{{ $group->account?->name ?? '-' }}</td>
                    @endunless
                    <td>
                        @foreach($group->dues as $due)
                            <span class="detail-row">
                                {{ $due->created_at_manual?->format('d.m.Y') ?? $due->created_at?->format('d.m.Y') ?? '-' }}
                                | {{ number_format($due->remaining_amount, 2, ',', '.') }} ₺
                                | {{ $due->description ?? '-' }}
                            </span>
                        @endforeach
                    </td>
                    <td class="text-right text-red">{{ number_format($group->total_remaining, 2, ',', '.') }} ₺</td>
                </tr>
            @empty
                <tr><td colspan="{{ $hideAccountNames ? 3 : 4 }}" class="text-center text-muted">Borçlu hesap bulunamadı.</td></tr>
            @endforelse
        </tbody>
        @if($groups->count())
        <tfoot>
            <tr>
                <td colspan="{{ $hideAccountNames ? 2 : 3 }}">TOPLAM</td>
                <td class="text-right text-red">{{ number_format($totalOverdue, 2, ',', '.') }} ₺</td>
            </tr>
        </tfoot>
        @endif
    </table>
@endsection
