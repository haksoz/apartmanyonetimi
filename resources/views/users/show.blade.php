@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">AidatCep kimliği, rol ve bağlı hesaplar.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Kullanıcılara Dön</a>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h5 class="text-sm font-semibold text-slate-900 mb-5">AidatCep kimliği</h5>
            <div class="grid gap-4 md:grid-cols-3 text-sm">
                <div>
                    <div class="text-xs text-slate-500 mb-1">Ad Soyad</div>
                    <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-1">E-posta</div>
                    <div class="font-semibold text-slate-900">{{ $user->email }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 mb-1">Telefon</div>
                    <div class="font-semibold text-slate-900">{{ $user->phone ?: '—' }}</div>
                </div>
            </div>
        </div>

        @if (! $user->isActivated() && $linkedAccounts->isEmpty())
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-5">Bilgileri Düzenle</h5>
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-5 md:grid-cols-3 mb-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">Ad Soyad</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">E-posta</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-2">Telefon</label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                            @error('phone')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                        Bilgileri Güncelle
                    </button>
                </form>
            </div>
        @endif

        @if ($linkedAccounts->isNotEmpty())
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-5">Bağlı Hesaplar</h5>
                <div class="space-y-2">
                    @foreach ($linkedAccounts as $account)
                        @php
                            $identityDiffers = $user->isActivated() && (
                                $account->name !== $user->name
                                || trim((string) $account->phone) !== trim((string) $user->phone)
                            );
                        @endphp
                        <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <a href="{{ route('accounts.show', $account) }}" class="font-medium text-sm text-slate-900 hover:text-slate-600">{{ $account->name }}</a>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $account->type_label }}
                                    </span>
                                    @if ($account->unit)
                                        <span>{{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No</span>
                                    @endif
                                    <span>Telefon: {{ $account->phone ?: '—' }}</span>
                                </div>
                            </div>
                            @if ($identityDiffers)
                                <form method="POST" action="{{ route('accounts.user.identity', $account) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        AidatCep adını ve telefonunu yaz
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! $user->isActivated())
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-2">Davet bağlantısı</h5>
                <p class="text-sm text-slate-500">Bu kişi henüz parolasını belirlemedi. Bağlantıyı kendisine iletin. Yeni bağlantı eskisini düşürür.</p>
                @if (session('invite_url'))
                    <p class="mt-4 break-all rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900">{{ session('invite_url') }}</p>
                @endif
                @error('invite')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <form method="POST" action="{{ route('users.invite', $user) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Bağlantıyı yenile</button>
                </form>
            </div>
        @endif
    </div>
@endsection
