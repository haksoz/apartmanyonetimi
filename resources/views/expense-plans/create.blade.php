@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-center gap-2 text-sm text-slate-400">
            <a href="{{ route('financial-plans.index') }}" class="hover:text-slate-600">Finansal Planlar</a>
            <span>/</span>
            <span class="text-slate-500">Gider Planı Oluştur</span>
        </div>
        <a href="{{ route('financial-plans.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Finansal Planlara Dön</a>
    </div>

    <form method="POST" action="{{ route('expense-plans.store') }}" class="space-y-4">
        @csrf
        @include('expense-plans._form')
    </form>


@endsection
