@extends('layouts.app')

@section('due_picker_autostart', '1')

@section('content')
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-950">Borçlandır</h1>
        <p class="mt-2 text-sm text-slate-500">Devam etmek için borç türünü seçin.</p>
        <a href="{{ request()->fullUrl() }}" data-due-account-type="{{ ($pickerTenant ?? false) ? 'tenant' : '' }}" class="mt-4 inline-block rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Borç Türünü Seçin</a>
        <a href="{{ route('dues.index') }}" class="ml-3 text-sm font-semibold text-slate-600">Borçlandırmalara Dön</a>
    </div>
@endsection
