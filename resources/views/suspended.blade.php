@extends('layouts.app')

@section('content')
    <div class="flex min-h-[60vh] items-center justify-center">
        <div class="max-w-md w-full rounded-2xl bg-white shadow-sm border border-slate-200 p-8 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-7 w-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-slate-900 mb-2">Hesabınız Pasife Alınmıştır</h1>
            <p class="text-sm text-slate-500 mb-6">
                Bu apartmana erişiminiz geçici olarak askıya alınmıştır.
                Detaylı bilgi için apartman yöneticinizle iletişime geçin.
            </p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Çıkış Yap
                </button>
            </form>
        </div>
    </div>
@endsection
