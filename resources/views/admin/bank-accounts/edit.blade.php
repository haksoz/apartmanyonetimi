@extends('layouts.app')

@section('title', 'Banka Hesabı Düzenle')

@section('content')
    <div class="max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('admin.bank-accounts.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Banka Bilgilerine Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Banka Hesabı Düzenle</h1>
        </div>

        @include('admin.bank-accounts._form', [
            'action' => route('admin.bank-accounts.update', $bankAccount),
            'method' => 'PATCH',
            'buttonText' => 'Güncelle',
            'bankAccount' => $bankAccount,
        ])
    </div>
@endsection
