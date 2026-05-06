@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kasa Ekle</h1>
        <p class="mt-1 text-sm text-slate-500">Nakit kasa, banka kasası veya farklı amaçlı yeni bir kasa tanımlayın.</p>
    </div>

    @include('cash.boxes.form', [
        'action' => route('cash-boxes.store'),
        'method' => 'POST',
        'cashBox' => null,
        'buttonText' => 'Kaydet',
    ])
@endsection
