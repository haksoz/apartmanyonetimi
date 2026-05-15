@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Daireler</h1>
            <p class="mt-1 text-sm text-slate-500">Apartmandaki tüm daireler ve özellikleri.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('units.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Daire Ekle</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3">Daire No</th>
                    <th class="px-5 py-3">Kat/Blok</th>
                    <th class="px-5 py-3 text-right">m²</th>
                    <th class="px-5 py-3 text-right">Pay Çarpanı</th>
                    <th class="px-5 py-3">Kat Maliki</th>
                    <th class="px-5 py-3">Kiracı</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($units as $unit)
                    <tr>
                        <td class="px-5 py-4 font-medium text-slate-950">{{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-5 py-4 text-slate-700">
                            @if($unit->floor || $unit->block)
                                {{ $unit->floor }}{{ $unit->floor && $unit->block ? ' / ' : '' }}{{ $unit->block }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right text-slate-700">
                            {{ $unit->square_meters ? number_format($unit->square_meters, 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-5 py-4 text-right text-slate-700">
                            {{ $unit->share_coefficient ? number_format($unit->share_coefficient, 4, ',', '.') : '-' }}
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            @if($unit->ownerAccount)
                                <a href="{{ route('accounts.show', $unit->ownerAccount) }}" class="text-emerald-600 hover:underline">{{ $unit->ownerAccount->name }}</a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-700">
                            @if($unit->occupantAccount)
                                <a href="{{ route('accounts.show', $unit->occupantAccount) }}" class="text-emerald-600 hover:underline">{{ $unit->occupantAccount->name }}</a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-3">
                                <a href="{{ route('units.show', $unit) }}" class="font-semibold text-slate-700 hover:text-slate-950">Detay</a>
                                <a href="{{ route('units.edit', $unit) }}" class="font-semibold text-slate-700 hover:text-slate-950">Düzenle</a>
                                <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Bu daireyi silmek istediğinize emin misiniz?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-semibold text-red-600 hover:text-red-700">Sil</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-slate-500">
                            Henüz daire eklenmemiş.
                            <a href="{{ route('units.create') }}" class="text-emerald-600 hover:underline">İlk daireyi ekleyin</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
