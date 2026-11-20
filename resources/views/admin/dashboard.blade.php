@extends('layouts.app')

@section('title', 'Admin Paneli')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Admin Paneli</h1>
        <p class="text-sm text-slate-500">Operasyonel özet ve yönetim.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-sky-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-sky-50 p-3">
                    <svg class="w-6 h-6 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Müşteriler ({{ $customerCount }})</div>
                    <div class="text-sm text-slate-500">Kayıtlı müşteriyi ve ilişkilerini gör.</div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.managers.index', ['view' => 'items']) }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-emerald-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-emerald-50 p-3">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.592-2.641m-3.958-5.599c.351.351.645.748.876 1.185M9 13.5V9.75a6 6 0 0112 0v3"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Abonelikler ({{ $subscriptionCount }})</div>
                    <div class="text-sm text-slate-500">Apartmanın abonelik geçmişini takip et.</div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.orders.index') }}" class="rounded-xl border border-slate-200 bg-white p-6 hover:border-amber-300 transition-colors">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-amber-50 p-3">
                    <svg class="w-6 h-6 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">Siparişler ({{ $orderCount }})</div>
                    <div class="text-sm text-slate-500">Siparişin ve tahsilatın durumunu takip et.</div>
                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <span class="text-amber-700">Bekleyen: {{ $pendingOrderCount }}</span>
                        <span class="text-emerald-700">Aktif: {{ $activeOrderCount }}</span>
                        <span class="text-slate-500">İptal: {{ $cancelledOrderCount }}</span>
                    </div>
                </div>
            </div>
        </a>
    </div>

@endsection
