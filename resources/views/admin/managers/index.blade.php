@extends('layouts.app')

@section('title', 'Abonelikler')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Abonelikler</h1>
            <p class="text-sm text-slate-500">Kullanıcı aboneliklerini ve apartman kotalarını takip et.</p>
        </div>
    </div>

    <div class="mb-6">
        <form method="GET" action="{{ route('admin.managers.index') }}" class="flex gap-3">
            <input type="text" name="search" value="{{ $search }}" placeholder="Ad veya e-posta ara" class="w-full max-w-md rounded-xl border border-slate-300 px-4 py-2 text-sm">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ara</button>
            @if ($search)
                <a href="{{ route('admin.managers.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Sıfırla</a>
            @endif
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kullanıcı</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Paket</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Dönem</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Apartman</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-700">Kota</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-700">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($managers as $manager)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $manager->name }}</div>
                            <div class="text-xs text-slate-500">{{ $manager->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $manager->subscription?->package?->name ?? 'Paket yok' }}
                        </td>
                        <td class="px-4 py-3 text-slate-700 capitalize">
                            {{ $manager->subscription?->period ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $quota->currentCount($manager) }}
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $quota->maxFor($manager) ?? 'Sınırsız' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.managers.show', $manager) }}" class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">Detay</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $managers->links() }}
    </div>
@endsection
