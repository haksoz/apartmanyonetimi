@extends('layouts.app')

@section('title', 'Veri İşlem Kaydı')

@section('content')
    @php
        $apartment = $operation->liveApartment();
    @endphp
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Veri işlem kaydı #{{ $operation->id }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $operation->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</p>
        </div>
        <a href="{{ route('admin.data-operations.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Listeye dön</a>
    </div>

    <dl class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">İşlem</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->actionLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Sonuç</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->resultLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kullanıcı</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->actor_name ?: ($operation->user?->name ?? '—') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">İşlem anındaki apartman</dt>
            <dd class="mt-1 text-sm text-slate-900">#{{ $operation->source_apartment_id ?: '—' }} {{ $operation->apartment_name }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">İşlem öncesi daire sayısı</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->unit_count_before ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">İstenen daire sayısı</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->requested_unit_count ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Yedekleme kontrolü</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->archiveLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Arşiv referansı</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->archive_reference ?: 'Arşiv referansı yok' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Saklama bitişi</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->archive_retain_until?->format('d.m.Y H:i') ?: 'Belirlenmedi' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Apartman kaydı</dt>
            <dd class="mt-1 text-sm text-slate-900">
                {{ $operation->apartmentPresenceLabel() }}
                @if ($apartment && ! $apartment->trashed())
                    · güncel daire sayısı {{ $apartment->unit_count }}
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">İşlem anındaki abonelik</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->recordedSubscriptionLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Güncel abonelik kaydı</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->liveSubscriptionRecordLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Güncel abonelik kalemleri</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->liveSubscriptionItemsLabel() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Güncel ücretli dönem</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->livePaidPeriodLabel() }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kapsam</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->scope ?: '—' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Not</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ $operation->note ?: '—' }}</dd>
        </div>
    </dl>
@endsection
