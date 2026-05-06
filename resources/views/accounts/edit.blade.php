@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Hesabı Düzenle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $account->name }} hesabını güncelleyin.</p>
        </div>
        <a href="{{ route('accounts.show', $account) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detaya Dön</a>
    </div>

    @include('accounts.form', [
        'action' => route('accounts.update', $account),
        'method' => 'PUT',
        'account' => $account,
        'units' => $units,
        'buttonText' => 'Hesabı Güncelle',
    ])
@endsection
