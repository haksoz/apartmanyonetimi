@extends('layouts.app')

@section('title', 'Fatura bilgileri')

@section('content')
    <div class="mx-auto min-w-0 max-w-3xl"><a href="{{ route('admin.customers.show', $customer) }}" class="mb-4 inline-block text-sm font-semibold text-emerald-700">← Müşteriye dön</a>
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900">Fatura bilgileri</h1>
                <p class="mt-1 break-words text-sm text-slate-500">{{ $customer->name }} · Fatura profilleri</p>
            </div>
            <a href="{{ route('admin.customers.billing-profiles.create', $customer) }}" class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni profil</a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($profiles->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600">
                Bu müşterinin henüz kayıtlı fatura profili yok.
            </div>
        @else
            <div class="space-y-3">
                @foreach ($profiles as $profile)
                    <article class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1 break-words">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="min-w-0 font-semibold text-slate-900">{{ $profile->label }}</h2>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $profile->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $profile->is_active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">{{ \App\Models\BillingProfile::partyLabel($profile->party_type) }} · {{ $profile->legal_name }}</p>
                                @if ($profile->address)
                                    <p class="mt-1 text-sm text-slate-600">{{ $profile->address }}</p>
                                @endif
                                @if ($profile->party_type === \App\Models\BillingProfile::TYPE_CORPORATE)
                                    @if ($profile->tax_office)
                                        <p class="mt-1 text-sm text-slate-600">{{ $profile->tax_office }}</p>
                                    @endif
                                    @if ($profile->identity_number)
                                        <p class="mt-1 text-sm text-slate-600">Vergi no: {{ $profile->identity_number }}</p>
                                    @endif
                                @elseif ($profile->identity_number)
                                    <p class="mt-1 text-sm text-slate-600">Kimlik no: {{ $profile->identity_number }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a href="{{ route('admin.customers.billing-profiles.edit', [$customer, $profile]) }}" class="whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>
                                <form method="POST" action="{{ route('admin.customers.billing-profiles.active', [$customer, $profile]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="whitespace-nowrap rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
