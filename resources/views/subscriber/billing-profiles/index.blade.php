@extends('layouts.app')

@section('title', 'Fatura bilgileri')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Fatura bilgileri</h1>
                <p class="mt-1 text-sm text-slate-500">Bu kayıtlar size aittir. Sipariş açarken birini seçebilir veya yenisini oluşturabilirsiniz.</p>
            </div>
            <a href="{{ route('subscriber.billing-profiles.create') }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni profil</a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($profiles->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                Henüz fatura profiliniz yok.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($profiles as $profile)
                    <article class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold text-slate-900">{{ $profile->label }}</h2>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $profile->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $profile->is_active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ \App\Models\BillingProfile::partyLabel($profile->party_type) }}</p>
                                @if ($profile->legal_name)
                                    <p class="mt-1 text-sm text-slate-700">{{ $profile->legal_name }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('subscriber.billing-profiles.edit', $profile) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                                <form method="POST" action="{{ route('subscriber.billing-profiles.active', $profile) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                        {{ $profile->is_active ? 'Pasife al' : 'Yeniden kullan' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
