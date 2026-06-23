@php
$roleLabels = [
    App\Models\User::ROLE_SUPER_ADMIN => 'Süper Admin',
    App\Models\User::ROLE_ADMIN => 'Admin',
    App\Models\User::ROLE_ACCOUNTANT => 'Muhasebeci',
    App\Models\User::ROLE_SUPPORT => 'Destek',
];
@endphp

@extends('layouts.app')

@section('title', 'Admin Kullanıcıları')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Admin Kullanıcıları</h1>
            <p class="text-sm text-slate-500">Admin paneline erişimi olan kullanıcıları yönet.</p>
        </div>
        <a href="{{ route('admin.admin-users.create') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Yeni Kullanıcı</a>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4">
        <form method="GET" action="{{ route('admin.admin-users.index') }}" class="flex items-center gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Ad veya e-posta ara..." class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm sm:w-80">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search)
                <a href="{{ route('admin.admin-users.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-800">Temizle</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kullanıcı</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Rol</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Telefon</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kayıt Tarihi</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse ($adminUsers as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500">{{ $user->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">{{ $roleLabels[$user->role] ?? $user->role }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $user->phone ?: '-' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $user->created_at->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.admin-users.edit', $user) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Düzenle</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Admin kullanıcısı bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $adminUsers->links() }}
    </div>
@endsection
