@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-slate-950">Nereye gireceksiniz?</h1>
            <p class="mt-2 text-sm text-slate-500">Daireleriniz ve yönettiğiniz apartmanlar ayrı açılır.</p>
        </div>

        @if ($residences->isNotEmpty())
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Dairelerim</h2>
            <div class="mb-8 grid gap-4 md:grid-cols-2">
                @foreach ($residences as $apartment)
                    @include('apartments.partials.place-card', ['apartment' => $apartment, 'destination' => 'dairem', 'action' => 'Daireme gir'])
                @endforeach
            </div>
        @endif

        @if ($managed->isNotEmpty())
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Yönettiğim apartmanlar</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($managed as $apartment)
                    @include('apartments.partials.place-card', ['apartment' => $apartment, 'destination' => 'ozet', 'action' => 'Yönetime gir'])
                @endforeach
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-sm font-semibold">
            @if (auth()->user()->canPurchaseSubscription())
                <a href="{{ route('subscriber.dashboard') }}" class="text-slate-700 hover:text-slate-950">Abone Paneli</a>
            @endif
            @if (auth()->user()->isSubscriber())
                <a href="{{ route('apartments.create') }}" class="text-slate-700 hover:text-slate-950">Yeni apartman oluştur</a>
            @endif
        </div>
    </div>
@endsection
