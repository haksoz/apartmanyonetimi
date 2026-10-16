@extends('layouts.app')

@section('title', 'Abone Paneli')

@section('content')
    @if ($apartments->isNotEmpty())
        <section class="mb-8">
            @if ($showApartmentCreate && $apartmentLimit !== null && ! $canCreateApartment)
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Apartman açma hakkınız {{ $apartmentCount }}/{{ $apartmentLimit }} dir. Tanımlı limitinize ulaştınız.
                    <span class="mt-1 block">Daha fazla apartman yönetimi için AidatCep ile iletişime geçin.</span>
                </div>
            @elseif ($showApartmentCreate && $apartmentLimit !== null && $canCreateApartment)
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Apartman açma hakkınız {{ $apartmentCount }}/{{ $apartmentLimit }} dir. Yeni apartman oluşturabilirsiniz.
                    <a href="{{ route('subscriber.apartments.create') }}" class="font-semibold text-emerald-800 underline">+ Yeni Apartman Oluştur</a>
                </div>
            @elseif ($showApartmentCreate && $apartmentLimit === null)
                <div class="mb-4">
                    <a href="{{ route('subscriber.apartments.create') }}" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Apartman Oluştur</a>
                </div>
            @endif
            @include('subscriber.partials.apartment-cards', ['showDetails' => true])
        </section>
    @elseif ($showApartmentCreate)
        <section class="mb-8">
            <a href="{{ route('subscriber.apartments.create') }}" class="inline-flex rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Apartman Oluştur</a>
        </section>
    @endif

    @if ($residenceAccounts->isNotEmpty())
        <section>
            <h2 class="text-lg font-bold text-slate-950">Dairem</h2>
            <p class="mt-1 text-sm text-slate-500">Oturduğunuz veya sahip olduğunuz daireler.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($residenceAccounts as $account)
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <p class="text-sm font-semibold text-slate-500">{{ $account->apartment->name }}</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">
                            @if ($account->unit)
                                Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}
                            @else
                                {{ $account->name }}
                            @endif
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $account->type_label }}</p>
                        <form method="POST" action="{{ route('current-apartment.update') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="apartment_id" value="{{ $account->apartment_id }}">
                            <input type="hidden" name="destination" value="dairem">
                            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Daire durumum</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
