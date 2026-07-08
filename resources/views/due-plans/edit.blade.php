@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Aidat Planı Düzenle</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $duePlan->name }}</p>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm max-w-2xl">
        <form method="POST" action="{{ route('due-plans.update', $duePlan) }}" class="space-y-5">
            @csrf
            @method('PUT')
            @include('due-plans._form', ['plan' => $duePlan])
            <div class="flex items-center justify-between gap-3 pt-2">
                <div class="flex gap-3">
                    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Güncelle</button>
                    <a href="{{ route('due-plans.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">İptal</a>
                </div>
                <form method="POST" action="{{ route('due-plans.destroy', $duePlan) }}"
                      onsubmit="return confirm('Plan silinsin mi? Geçmiş oluşturulan aylar kayıtlarda tutulmaya devam eder.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Planı Sil</button>
                </form>
            </div>
        </form>
    </div>
@endsection
