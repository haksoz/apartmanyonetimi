@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kasa Hareketi Ekle</h1>
        <p class="mt-1 text-sm text-slate-500">Kasa tanımını açıklama ile yapın, varsa ilgili hesabı seçin.</p>
    </div>

    @include('cash.form', [
        'action' => route('cash.store'),
        'method' => 'POST',
        'transaction' => null,
        'accounts' => $accounts,
        'buttonText' => 'Kaydet',
    ])
@endsection
