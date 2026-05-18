@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kullanıcı Yönetimi</h1>
            <p class="mt-1 text-sm text-slate-500">Apartmana erişimi olan tüm kullanıcıların rollerini yönetin.</p>
        </div>
        @if ($availableAccounts->isNotEmpty())
            <button type="button" onclick="document.getElementById('add-from-account').classList.toggle('hidden')"
                class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition-colors">
                + Hesaptan Kullanıcı Ekle
            </button>
        @endif
    </div>

    @if ($availableAccounts->isEmpty() && $users->isEmpty())
        <div class="mb-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 border border-amber-200">
            <p class="font-medium">Henüz kullanıcı yok.</p>
            <p class="mt-1">Önce <a href="{{ route('accounts.index') }}" class="underline font-medium">Hesaplar</a> sayfasından daire bilgilerini doldurun, sonra buradan kullanıcı oluşturun.</p>
        </div>
    @endif

    {{-- Hesaptan Ekle Formu --}}
    @if ($availableAccounts->isNotEmpty())
        <div id="add-from-account" class="hidden mb-6 rounded-2xl bg-white p-5 shadow-sm border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900 mb-4">Hesaptan Kullanıcı Oluştur</h3>
            <p class="text-xs text-slate-500 mb-4">Bilgileri doldurulmuş hesapları seçin. Seçilen hesapların bilgileriyle kullanıcı oluşturulacak.</p>
            <form method="POST" action="{{ route('users.invite') }}">
                @csrf
                <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
                    @foreach ($availableAccounts as $account)
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="accounts[]" value="{{ $account->id }}" class="rounded border-slate-300">
                            <div class="flex-1">
                                <div class="font-medium text-sm text-slate-900">{{ $account->name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $account->unit ? str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT).' No · ' : '' }}
                                    {{ $account->type_label }}
                                    @if ($account->email) · {{ $account->email }}@endif
                                    @if ($account->phone) · {{ $account->phone }}@endif
                                </div>
                            </div>
                            @if (! $account->email)
                                <span class="text-xs px-2 py-1 rounded bg-red-50 text-red-600">Eksik Bilgi</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Seçilenleri Kullanıcı Yap
                    </button>
                    <button type="button" onclick="document.getElementById('add-from-account').classList.add('hidden')"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        İptal
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Legend --}}
    <div class="mb-5 flex flex-wrap gap-3 text-xs">
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-500">
            <span class="w-2 h-2 rounded-full bg-slate-300 inline-block"></span> Hesapsız — Dışarıdan yöneten
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-50 text-blue-700">
            <span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span> Sakin — Sadece kendi hesabını görür
        </span>
        <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700">
            <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> Yönetici — Tüm apartman verilerine erişir
        </span>
    </div>

    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">Daire</th>
                    <th class="px-5 py-3 text-left">Kullanıcı</th>
                    <th class="px-5 py-3 text-left">Hesap Bağı</th>
                    <th class="px-5 py-3 text-left">Rol</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    @php
                        $role = $user->pivot->role;
                        $account = $linkedAccounts[$user->id] ?? null;
                        $isCurrentUser = $user->id === auth()->id();
                    @endphp
                    <tr class="hover:bg-slate-50 {{ $isCurrentUser ? 'bg-slate-50/50' : '' }}">
                        <td class="px-5 py-3.5">
                            @if ($account && $account->unit)
                                <span class="font-semibold text-slate-800">{{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-xs text-slate-400 ml-1">No</span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="font-medium text-slate-900">{{ $user->name }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $user->email }}</div>
                        </td>
                        <td class="px-5 py-3.5">
                            @if ($account)
                                <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700">
                                    {{ $account->type_label }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-slate-100 text-slate-400">
                                    Hesapsız
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                @if (! $isCurrentUser)
                                    <form method="POST" action="{{ route('users.role', $user) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" onchange="this.form.submit()"
                                            class="text-xs rounded-lg border px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer {{ $role === 'owner' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                            <option value="resident" {{ $role === 'resident' ? 'selected' : '' }}>Sakin</option>
                                            <option value="owner"    {{ $role === 'owner'    ? 'selected' : '' }}>Yönetici</option>
                                        </select>
                                    </form>
                                    <button type="button" onclick="openPasswordModal({{ $user->id }}, '{{ $user->name }}')"
                                        class="text-xs px-2 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" title="Şifre Belirle">
                                        🔑 Şifre
                                    </button>
                                @else
                                    <span class="text-xs px-2 py-1.5 rounded-lg bg-slate-100 text-slate-500 border border-slate-200">
                                        {{ $role === 'owner' ? 'Yönetici' : 'Sakin' }} (Siz)
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-400">
                            Henüz apartmana kullanıcı eklenmemiş.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Şifre Belirle Modal --}}
    <div id="password-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900 mb-1" id="modal-title">Şifre Belirle</h3>
            <p class="text-sm text-slate-500 mb-4" id="modal-user">Kullanıcı: <span></span></p>

            <form method="POST" action="" id="password-form">
                @csrf
                @method('PATCH')
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-600">Yeni Şifre</label>
                        <div class="flex gap-2 mt-1">
                            <input type="text" name="password" id="password-input" required
                                class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300"
                                placeholder="Şifre girin veya üretin">
                            <button type="button" onclick="generatePassword()"
                                class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                                🎲 Üret
                            </button>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Kullanıcıya bu şifreyi güvenli bir şekilde bildirin.</p>
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="submit" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                        Şifreyi Kaydet
                    </button>
                    <button type="button" onclick="closePasswordModal()"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal(userId, userName) {
            document.getElementById('modal-user').querySelector('span').textContent = userName;
            document.getElementById('password-form').action = `/users/${userId}/password`;
            document.getElementById('password-modal').classList.remove('hidden');
            generatePassword();
        }

        function closePasswordModal() {
            document.getElementById('password-modal').classList.add('hidden');
        }

        function generatePassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
            let password = '';
            for (let i = 0; i < 10; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('password-input').value = password;
        }

        // Modal dışına tıklayınca kapat
        document.getElementById('password-modal').addEventListener('click', function(e) {
            if (e.target === this) closePasswordModal();
        });
    </script>
@endsection
