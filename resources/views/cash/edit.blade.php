@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kasa Hareketi Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">Kasa hareketinin açıklama, hesap, tutar ve aktiflik bilgisini düzenleyin.</p>
    </div>

    @include('cash.form', [
        'action' => route('cash.update', $transaction),
        'method' => 'PUT',
        'transaction' => $transaction,
        'accounts' => $accounts,
        'buttonText' => 'Güncelle',
    ])
@endsection
