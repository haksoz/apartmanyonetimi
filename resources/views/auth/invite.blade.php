@extends('layouts.guest')

@section('title', 'Davet')

@section('content')
    <div class="w-full max-w-md rounded-2xl bg-white p-4 shadow-sm sm:p-6">
        <h1 class="text-xl font-bold text-slate-950">Hesabınızı açın</h1>
        @if (! $invitation)
            <p class="mt-3 text-sm text-slate-600">Davet bağlantısı geçersiz veya süresi dolmuş. Yeni bağlantı için apartman yöneticinize başvurun.</p>
        @else
            <p class="mt-1 text-sm text-slate-500">{{ $invitation->user->name }}, parolanızı belirleyerek AidatCep hesabınızı açın.</p>
            <form method="POST" action="{{ route('invite.accept', $token) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="password" class="text-sm font-medium text-slate-700">Parola</label>
                    <input id="password" name="password" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-medium text-slate-700">Parola tekrar</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-0.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                @error('token')<div class="text-sm text-red-600">{{ $message }}</div>@enderror
                <button type="submit" class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Hesabı aç</button>
            </form>
        @endif
    </div>
@endsection
