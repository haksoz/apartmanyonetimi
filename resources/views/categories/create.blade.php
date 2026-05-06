@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kategori Ekle</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $apartment->name }} için yeni kategori oluşturun.</p>
        </div>
        <a href="{{ route('categories.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kategorilere Dön</a>
    </div>

    @include('categories.form', [
        'action' => route('categories.store'),
        'method' => 'POST',
        'category' => null,
        'buttonText' => 'Kategori Kaydet',
    ])
@endsection
