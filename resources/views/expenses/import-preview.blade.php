@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Gider İçe Aktar — Önizleme</h1>
        <p class="mt-1 text-sm text-slate-500">Verileri kontrol edin ve içe aktarın.</p>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Toplam Kayıt</div>
            <div class="text-xl font-bold text-slate-900">{{ count($transactions) }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Geçerli</div>
            <div class="text-xl font-bold text-emerald-600">{{ $validCount }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Hatalı</div>
            <div class="text-xl font-bold {{ $invalidCount > 0 ? 'text-red-600' : 'text-slate-400' }}">{{ $invalidCount }}</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="text-xs text-slate-500 mb-1">Toplam Tutar</div>
            <div class="text-xl font-bold text-slate-900">{{ number_format($totalAlacak, 2, ',', '.') }} TL</div>
        </div>
    </div>

    {{-- Financial Summary --}}
    <div class="rounded-xl bg-slate-50 p-4 mb-6">
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <div class="text-xs text-slate-500">Toplam Gider</div>
                <div class="text-lg font-semibold text-slate-900">{{ number_format($totalAlacak, 2, ',', '.') }} TL</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Ödenen</div>
                <div class="text-lg font-semibold text-emerald-600">{{ number_format($totalBorc, 2, ',', '.') }} TL</div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Kalan Borç</div>
                <div class="text-lg font-semibold text-amber-600">{{ number_format($totalRemaining, 2, ',', '.') }} TL</div>
            </div>
        </div>
    </div>

    @if (!empty($validationErrors))
        <div class="rounded-xl bg-red-50 border border-red-200 p-4 mb-6">
            <h3 class="text-sm font-semibold text-red-800 mb-2">Hatalar ({{ count($validationErrors) }})</h3>
            <ul class="text-xs text-red-700 space-y-1 max-h-32 overflow-y-auto">
                @foreach ($validationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Preview Table --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Kayıtlar</h3>
            @if (count($transactions) > 50)
                <span class="text-xs text-amber-600">İlk 50 kayıt gösteriliyor (toplam {{ count($transactions) }})</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Satır</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Tarih</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Açıklama</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Kategori</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Alacak</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Borç</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500 text-right">Kalan</th>
                        <th class="px-4 py-3 text-xs font-semibold text-slate-500">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach (array_slice($transactions, 0, 50) as $t)
                        <tr class="{{ !$t['is_valid'] ? 'bg-red-50' : '' }}">
                            <td class="px-4 py-3 text-slate-700">{{ $t['row'] }}</td>
                            <td class="px-4 py-3 text-slate-700 tabular-nums">{{ \Carbon\Carbon::parse($t['date'])->format('d.m.Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="text-slate-900">{{ $t['description'] }}</div>
                                @if ($t['account_name'])
                                    <div class="text-xs text-slate-500">Hesap: {{ $t['account_name'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($t['category_matched'])
                                    <span class="text-slate-700">{{ $t['category_name'] }}</span>
                                @else
                                    <span class="text-amber-600" title="Orijinal: {{ $t['category_name'] }}">
                                        Diğer
                                        @if ($t['category_name'])
                                            <span class="text-xs">(was: {{ $t['category_name'] }})</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($t['alacak'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($t['borc'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium {{ $t['remaining'] > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                {{ number_format($t['remaining'], 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3">
                                @if (!$t['is_valid'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">
                                        Hatalı
                                    </span>
                                @elseif ($t['remaining'] == 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">
                                        Tam Ödenmiş
                                    </span>
                                @elseif ($t['borc'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                        Kısmen Ödenmiş
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                                        Ödenmemiş
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        <form method="POST" action="{{ route('expenses.import-confirm') }}" class="flex-1">
            @csrf
            <button type="submit" class="w-full rounded-xl {{ $validCount > 0 ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed' }} px-4 py-3 text-sm font-semibold"
                    {{ $validCount == 0 ? 'disabled' : '' }}>
                {{ $validCount > 0 ? $validCount . ' Kaydı İçe Aktar' : 'Geçerli Kayıt Yok' }}
            </button>
        </form>
        <a href="{{ route('expenses.import') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Geri Dön
        </a>
        <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Vazgeç
        </a>
    </div>
@endsection
