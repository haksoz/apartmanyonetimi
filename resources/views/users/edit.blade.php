@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-900">← Kullanıcılara Dön</a>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Kullanıcı Düzenle: {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Bilgileri güncelleyin ve hesap bağlantılarını yönetin.</p>
    </div>

    {{-- Kullanıcı Bilgileri Formu --}}
    <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200 mb-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Bilgiler</h2>
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PATCH')

            <div class="grid gap-5 md:grid-cols-3 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">İsim</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('name')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">E-posta</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('email')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Telefon</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none">
                    @error('phone')<div class="mt-2 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">
                    Bilgileri Güncelle
                </button>
            </div>
        </form>
    </div>

    {{-- Bağlı Hesaplar --}}
    @if ($linkedAccounts->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200 mb-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Bağlı Hesaplar</h2>
            <div class="space-y-2">
                @foreach ($linkedAccounts as $account)
                    <div class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50">
                        <div class="flex items-center gap-3">
                            <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700">{{ $account->type_label }}</span>
                            @if ($account->unit)
                                <span class="text-xs text-slate-500">{{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 px-3 py-1 rounded-lg border border-red-200 hover:bg-red-50"
                                onclick="return confirm('{{ $account->name }} hesabından bu kullanıcıyı ayırmak istediğinize emin misiniz?')">
                                Ayır
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Hesap Bağla --}}
    @if ($availableAccounts->isNotEmpty())
        <div class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Hesap Bağla</h2>
            <form method="POST" action="{{ route('users.attach-accounts', $user) }}">
                @csrf
                <div class="space-y-2 max-h-64 overflow-y-auto border border-slate-200 rounded-xl p-3 mb-4">
                    @foreach ($availableAccounts as $account)
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="account_ids[]" value="{{ $account->id }}" class="rounded border-slate-300">
                            <div class="flex-1">
                                <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' No · ' : '' }}
                                    {{ $account->type_label }}
                                    @if ($account->email) · {{ $account->email }}@endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                    Seçilen Hesapları Bağla
                </button>
            </form>
        </div>
    @endif
@endsection
