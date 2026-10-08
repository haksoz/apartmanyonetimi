@extends('layouts.app')

@section('title', $profile->exists ? 'Fatura profilini düzenle' : 'Yeni fatura profili')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('subscriber.billing-profiles.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Fatura bilgilerine dön</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $profile->exists ? 'Fatura profilini düzenle' : 'Yeni fatura profili' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Ad ve tür yeterlidir. Diğer alanları doldurmak zorunda değilsiniz.</p>
        </div>

        <form method="POST" action="{{ $profile->exists ? route('subscriber.billing-profiles.update', $profile) : route('subscriber.billing-profiles.store') }}" class="rounded-2xl border border-slate-200 bg-white p-6">
            @csrf
            @if ($profile->exists)
                @method('PUT')
            @endif
            @include('subscriber.billing-profiles.fields', ['profile' => $profile])
            <div class="mt-6 flex justify-end">
                <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Kaydet</button>
            </div>
        </form>
    </div>
@endsection
