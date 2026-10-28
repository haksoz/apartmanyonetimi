@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8" role="alert">
        <h1 class="text-xl font-semibold text-slate-950">PDF oluşturulamadı</h1>
        <p class="mt-3 text-sm text-slate-600">{{ $message }}</p>
        <p class="mt-2 text-sm text-slate-600">Önceki sayfaya dönerek verilerinizi görüntüleyebilir veya Excel olarak indirebilirsiniz. Seçtiğiniz filtreler korunur.</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ request()->fullUrl() }}" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Tekrar Dene</a>
            <a href="{{ $returnUrl }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Önceki Sayfaya Dön</a>
        </div>
    </div>
@endsection
