@extends('layouts.app')

@section('title', 'Yeni Banka Hesabı')

@section('content')
    <div class="max-w-2xl">
        <div class="mb-6">
            <a href="{{ route('admin.bank-accounts.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Banka Bilgilerine Dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">Yeni Banka Hesabı</h1>
        </div>

        @include('admin.bank-accounts._form', [
            'action' => route('admin.bank-accounts.store'),
            'method' => 'POST',
            'buttonText' => 'Kaydet',
        ])
    </div>
@endsection
