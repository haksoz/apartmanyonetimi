@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Kullanıcı detayları ve hesap bağlantıları.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Kullanıcılara Dön</a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-4">

        {{-- Kullanıcı Bilgileri --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <h5 class="text-sm font-semibold text-slate-900">Kullanıcı Bilgileri</h5>
            </div>
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

        {{-- Düzenle Formu --}}
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
                @if ($linkedAccounts->isNotEmpty())
                    <div class="mb-5">
                        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 cursor-pointer">
                            <input type="checkbox" name="sync_account_info" value="1" class="mt-0.5 rounded border-slate-300">
                            <div>
                                <div class="text-sm font-semibold text-amber-800">Bağlı hesap bilgilerini de güncelle</div>
                                <div class="text-xs text-amber-700 mt-1">
                                    @if ($linkedAccounts->count() === 1)
                                        <strong>"{{ $linkedAccounts->first()->name }}"</strong> hesabının ad, telefon ve e-posta bilgileri de güncellenecektir.
                                    @else
                                        Bağlı {{ $linkedAccounts->count() }} hesabın
                                        ({{ $linkedAccounts->pluck('name')->join(', ') }})
                                        ad, telefon ve e-posta bilgileri de güncellenecektir.
                                    @endif
                                </div>
                            </div>
                        </label>
                    </div>
                @endif
                <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                    Bilgileri Güncelle
                </button>
            </form>
        </div>

        {{-- Bağlı Hesaplar --}}
        @if ($linkedAccounts->isNotEmpty())
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-5">Bağlı Hesaplar</h5>
                <div class="space-y-2">
                    @foreach ($linkedAccounts as $account)
                        <div class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $account->type_label }}
                                </span>
                                @if ($account->unit)
                                    <span class="text-xs text-slate-500">{{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('accounts.user.destroy', $account) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors"
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
            <div class="rounded-2xl bg-white p-6 shadow-sm">
                <h5 class="text-sm font-semibold text-slate-900 mb-3">Kullanıcıya Tanımlanabilecek Hesaplar</h5>
                <p class="text-sm text-slate-500 mb-5">Boş bırakabilirsiniz. İsterseniz sonradan da hesap tanımlayabilirsiniz.</p>
                <form method="POST" action="{{ route('users.attach-accounts', $user) }}" id="attach-form">
                    @csrf
                    <div class="space-y-2 max-h-64 overflow-y-auto border border-slate-200 rounded-xl p-3 mb-4">
                        @foreach ($availableAccounts as $account)
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="account_ids[]" value="{{ $account->id }}"
                                    class="rounded border-slate-300 attach-checkbox"
                                    data-action="{{ route('accounts.user.store', $account) }}">
                                <div class="flex-1">
                                    <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' No · ' : '' }}
                                        {{ $account->type_label }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="mb-4 hidden" id="sync-info-box">
                        <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 cursor-pointer">
                            <input type="checkbox" name="sync_account_info" value="1" class="mt-0.5 rounded border-slate-300">
                            <div>
                                <div class="text-sm font-semibold text-amber-800">Hesap bilgilerini güncelle</div>
                                <div class="text-xs text-amber-700 mt-1">Seçili hesabın ad, telefon ve e-posta bilgileri kullanıcı bilgileriyle güncellenecektir.</div>
                            </div>
                        </label>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                        Seçilen Hesapları Bağla
                    </button>
                </form>
            </div>
        @endif

        {{-- Şifre Güncelle --}}
        <div class="rounded-2xl bg-white p-6 shadow-sm">
            <h5 class="text-sm font-semibold text-slate-900 mb-5">Şifre Belirle</h5>
            <form method="POST" action="{{ route('users.password', $user) }}">
                @csrf
                @method('PATCH')
                <div class="flex gap-3 items-end">
                    <div class="flex-1 max-w-sm">
                        <label class="block text-sm font-medium text-slate-600 mb-2">Yeni Şifre</label>
                        <input type="text" name="password" id="password-input" required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-950 focus:outline-none"
                            placeholder="Şifre girin veya üretin">
                    </div>
                    <button type="button" onclick="generatePassword()"
                        class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Üret
                    </button>
                    <button type="submit" class="rounded-xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                        Kaydet
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-2">Kullanıcıya bu şifreyi güvenli bir şekilde bildirin.</p>
            </form>
        </div>

    </div>

    <script>
        document.querySelectorAll('.attach-checkbox').forEach(function(cb) {
            cb.addEventListener('change', function() {
                const anyChecked = document.querySelectorAll('.attach-checkbox:checked').length > 0;
                document.getElementById('sync-info-box').classList.toggle('hidden', !anyChecked);
            });
        });

        function generatePassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            let password = '';
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('password-input').value = password;
        }
    </script>
@endsection
