@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesap Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için daire sakini veya tedarikçi hesabı oluşturun.</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaplara Dön</a>
    </div>

    @include('accounts.form', [
        'action' => route('accounts.store'),
        'method' => 'POST',
        'account' => null,
        'units' => $units,
        'buttonText' => 'Hesabı Kaydet',
    ])
@endsection
