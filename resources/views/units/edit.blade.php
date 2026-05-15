@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Daire Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ str_pad($unit->unit_no, 2, '0', STR_PAD_LEFT) }} no.lu daire özelliklerini güncelleyin.</p>
        </div>
        <a href="{{ route('units.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Listeye Dön</a>
    </div>

    @include('units.form', [
        'action' => route('units.update', $unit),
        'method' => 'PUT',
        'unit' => $unit,
        'buttonText' => 'Daireyi Güncelle',
    ])
@endsection
