@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Kullanıcı Yönetimi</h1>
            <p class="mt-1 text-sm text-slate-500">Apartmana erişimi olan tüm kullanıcıların rollerini yönetin.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('users.create') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 text-center">
                + Yeni Kullanıcı Oluştur
            </a>
        </div>
    </div>

    @if ($availableAccounts->isEmpty() && $users->isEmpty())
        <div class="mb-6 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 border border-amber-200">
            <p class="font-medium">Henüz kullanıcı yok.</p>
            <p class="mt-1">Önce <a href="{{ route('accounts.index') }}" class="underline font-medium">Hesaplar</a> sayfasından daire bilgilerini doldurun, sonra buradan kullanıcı oluşturun.</p>
        </div>
    @endif

    {{-- Desktop Table --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Daire</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Kullanıcı</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Hesap</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Rol</th>
                    <th class="px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-slate-500 text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    @php
                        $role = $user->pivot->role;
                        $isActive = (bool) ($user->pivot->is_active ?? true);
                        $userAccounts = $linkedAccounts[$user->id] ?? collect();
                        $isCurrentUser = $user->id === auth()->id();
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors {{ ! $isActive ? 'opacity-60' : '' }}">
                        <td class="px-5 py-4">
                            @if ($userAccounts->isNotEmpty())
                                <div class="space-y-1">
                                    @foreach ($userAccounts->take(3) as $acc)
                                        @if ($acc->unit)
                                            <div class="font-semibold text-slate-900">{{ str_pad($acc->unit->unit_no, 2, '0', STR_PAD_LEFT) }} No</div>
                                        @endif
                                    @endforeach
                                    @if ($userAccounts->count() > 3)
                                        <div class="text-xs text-slate-400">+{{ $userAccounts->count() - 3 }} daha</div>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ $user->email }}</div>
                            @if ($user->phone)
                                <div class="text-xs text-slate-400 mt-0.5">{{ $user->phone }}</div>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if ($userAccounts->isNotEmpty())
                                <div class="space-y-1">
                                    @foreach ($userAccounts->take(3) as $acc)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $acc->type_label }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>Hesapsız
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            @if (! $isActive)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Pasif
                                </span>
                            @elseif (! $isCurrentUser)
                                <form method="POST" action="{{ route('users.role', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" onchange="this.form.submit()"
                                        class="text-xs rounded-lg border px-2 py-1.5 focus:outline-none cursor-pointer {{ $role === 'owner' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                                        <option value="member" {{ $role === 'member' ? 'selected' : '' }}>Üye</option>
                                        <option value="owner"    {{ $role === 'owner'    ? 'selected' : '' }}>Yönetici</option>
                                    </select>
                                </form>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $role === 'owner' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">
                                    {{ $role === 'owner' ? 'Yönetici' : 'Üye' }} (Siz)
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                @if (! $isCurrentUser)
                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors {{ $isActive ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                                            {{ $isActive ? 'Pasife Al' : 'Aktive Et' }}
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('users.show', $user) }}"
                                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                                    Detay
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400">Henüz apartmana kullanıcı eklenmemiş.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($users as $user)
            @php
                $role = $user->pivot->role;
                $isActive = (bool) ($user->pivot->is_active ?? true);
                $userAccounts = $linkedAccounts[$user->id] ?? collect();
                $isCurrentUser = $user->id === auth()->id();
            @endphp
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200 {{ ! $isActive ? 'opacity-60' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="font-bold text-slate-900">{{ $user->name }}</div>
                        <div class="text-sm text-slate-500">{{ $user->email }}</div>
                        @if ($user->phone)
                            <div class="text-sm text-slate-400">{{ $user->phone }}</div>
                        @endif
                    </div>
                    <div>
                        @if (! $isActive)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Pasif
                            </span>
                        @elseif ($role === 'owner')
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Yönetici
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>Üye
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Daire</div>
                        @if ($userAccounts->isNotEmpty())
                            @foreach ($userAccounts->take(3) as $acc)
                                <div class="font-medium text-slate-900">{{ $acc->unit ? str_pad($acc->unit->unit_no, 2, '0', STR_PAD_LEFT).' No' : '—' }}</div>
                            @endforeach
                            @if ($userAccounts->count() > 3)
                                <div class="text-xs text-slate-400">+{{ $userAccounts->count() - 3 }} daha</div>
                            @endif
                        @else
                            <div class="font-medium text-slate-900">—</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Hesap</div>
                        @if ($userAccounts->isNotEmpty())
                            @foreach ($userAccounts->take(3) as $acc)
                                <div class="font-medium text-slate-900">{{ $acc->type_label }}</div>
                            @endforeach
                        @else
                            <div class="font-medium text-slate-900">Hesapsız</div>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2">
                    @if (! $isCurrentUser)
                        <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="w-full rounded-lg border px-3 py-2.5 text-sm font-semibold text-center {{ $isActive ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                                {{ $isActive ? 'Pasife Al' : 'Aktive Et' }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('users.show', $user) }}"
                        class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">
                        Detay
                    </a>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz apartmana kullanıcı eklenmemiş.
            </div>
        @endforelse
    </div>

@endsection
