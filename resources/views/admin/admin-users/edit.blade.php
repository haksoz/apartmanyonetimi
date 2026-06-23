@php
$roleOptions = [
    App\Models\User::ROLE_SUPER_ADMIN => 'Süper Admin',
    App\Models\User::ROLE_ADMIN => 'Admin',
    App\Models\User::ROLE_ACCOUNTANT => 'Muhasebeci',
    App\Models\User::ROLE_SUPPORT => 'Destek',
];
@endphp

@extends('layouts.app')

@section('title', $adminUser->name)

@section('content')
    <div class="mb-6">
        <a href="{{ route('admin.admin-users.index') }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">← Admin kullanıcılarına dön</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $adminUser->name }}</h1>
    </div>

    <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
        <form method="POST" action="{{ route('admin.admin-users.update', $adminUser) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-slate-700">Ad Soyad</label>
                <input type="text" name="name" value="{{ old('name', $adminUser->name) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('name')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">E-posta</label>
                <input type="email" name="email" value="{{ old('email', $adminUser->email) }}" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('email')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Telefon</label>
                <input type="text" name="phone" value="{{ old('phone', $adminUser->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('phone')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Rol</label>
                <select name="role" required class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}" {{ old('role', $adminUser->role) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Yeni Şifre (değiştirmek için doldurun)</label>
                <input type="password" name="password" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm">
                @error('password')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="pt-4">
                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Güncelle</button>
            </div>
        </form>

        @if ($adminUser->id !== auth()->id())
            <form method="POST" action="{{ route('admin.admin-users.destroy', $adminUser) }}" onsubmit="return confirm('Bu kullanıcıyı silmek istediğine emin misin?')" class="mt-4 flex justify-end border-t border-slate-100 pt-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-xl bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100">Sil</button>
            </form>
        @endif
    </div>
@endsection
