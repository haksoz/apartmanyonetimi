@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kasa Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">Kasa açıklamasını, banka bilgilerini ve aktiflik durumunu düzenleyin.</p>
    </div>

    @include('cash.boxes.form', [
        'action' => route('cash-boxes.update', $cashBox),
        'method' => 'PUT',
        'cashBox' => $cashBox,
        'buttonText' => 'Güncelle',
    ])
@endsection
