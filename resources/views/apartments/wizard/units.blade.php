@extends('layouts.app')

@section('content')
    @include('apartments.wizard._steps', ['activeStep' => 3])

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Daire Bilgileri</h1>
        <p class="mt-1 text-sm text-slate-500">Dairelere ait kat, blok, metrekare ve pay katsayısı bilgilerini girin. Bu adımı atlayabilirsiniz.</p>
    </div>

    <form method="POST" action="{{ route('apartments.wizard.units.store', $apartment) }}" class="rounded-2xl bg-white p-6 shadow-sm">
        @csrf
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Daire No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Kat</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Blok</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">m²</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Pay Katsayısı</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($units as $unit)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $unit->unit_no }}</td>
                            <td class="px-4 py-3">
                                <input type="text" name="units[{{ $unit->id }}][floor]" value="{{ old('units.'.$unit->id.'.floor', $unit->floor) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" name="units[{{ $unit->id }}][block]" value="{{ old('units.'.$unit->id.'.block', $unit->block) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.01" name="units[{{ $unit->id }}][square_meters]" value="{{ old('units.'.$unit->id.'.square_meters', $unit->square_meters) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.0001" name="units[{{ $unit->id }}][share_coefficient]" value="{{ old('units.'.$unit->id.'.share_coefficient', $unit->share_coefficient) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-950 focus:outline-none">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <form method="POST" action="{{ route('apartments.wizard.units.skip', $apartment) }}" class="inline">
                @csrf
                <button type="submit" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Atla</button>
            </form>
            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">Kaydet ve Devam Et</button>
        </div>
    </form>
@endsection
