@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-950">Raporlar</h1>
        <p class="mt-1 text-sm text-slate-500">Apartman yönetimine ait tüm finansal raporlar ve çıktılar</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        {{-- Aylık Aidat Pano Tablosu --}}
        <a href="{{ route('reports.monthly-board') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-indigo-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center group-hover:bg-indigo-100 transition">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-indigo-700">Aylık Aidat Pano Tablosu</h2>
                    <p class="mt-1 text-xs text-slate-500">Belirli ay için pano çıktısı, PDF/Excel</p>
                </div>
            </div>
        </a>

        {{-- Cari Ekstreler --}}
        <a href="{{ route('reports.account-statement') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-violet-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-violet-50 flex items-center justify-center group-hover:bg-violet-100 transition">
                    <svg class="w-6 h-6 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-violet-700">Cari Ekstreler</h2>
                    <p class="mt-1 text-xs text-slate-500">Hesap bazlı hareket dökümü ve bakiye</p>
                </div>
            </div>
        </a>

        {{-- Gelir-Gider Raporu --}}
        <a href="{{ route('reports.income-expense') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-emerald-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center group-hover:bg-emerald-100 transition">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-emerald-700">Gelir-Gider Raporu</h2>
                    <p class="mt-1 text-xs text-slate-500">Dönem bazlı tahsilat ve gider karşılaştırması</p>
                </div>
            </div>
        </a>

        {{-- Borç Listesi --}}
        <a href="{{ route('reports.debt-list') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-red-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center group-hover:bg-red-100 transition">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l-4-4 4-4m6 8l4-4-4-4M3 12h18"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-red-600">Borç Listesi</h2>
                    <p class="mt-1 text-xs text-slate-500">Ödenmemiş ve gecikmiş aidat borçları</p>
                </div>
            </div>
        </a>

        {{-- Alacak Listesi --}}
        <a href="{{ route('reports.receivable-list') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-blue-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center group-hover:bg-blue-100 transition">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-blue-700">Alacak Listesi</h2>
                    <p class="mt-1 text-xs text-slate-500">Tedarikçi ve hesap alacakları</p>
                </div>
            </div>
        </a>

        {{-- Aidat Tahsilat Raporu --}}
        <a href="{{ route('reports.due-collection') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-teal-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center group-hover:bg-teal-100 transition">
                    <svg class="w-6 h-6 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-teal-700">Aidat Tahsilat Raporu</h2>
                    <p class="mt-1 text-xs text-slate-500">Yıllık daire × ay matris tablosu</p>
                </div>
            </div>
        </a>

        {{-- Gecikme Raporu --}}
        <a href="{{ route('reports.overdue') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-orange-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center group-hover:bg-orange-100 transition">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-orange-600">Gecikme Raporu</h2>
                    <p class="mt-1 text-xs text-slate-500">Vadesi geçmiş borçlar ve gecikme süreleri</p>
                </div>
            </div>
        </a>

        {{-- Borç Listesi --}}
        <a href="{{ route('reports.debt-list') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-rose-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-rose-50 flex items-center justify-center group-hover:bg-rose-100 transition">
                    <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-rose-600">Borç Listesi</h2>
                    <p class="mt-1 text-xs text-slate-500">Hesap bazında birleştirilmiş borçlar</p>
                </div>
            </div>
        </a>

        {{-- Yıllık Faaliyet Raporu --}}
        <a href="{{ route('reports.annual-activity') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-slate-500 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-slate-100 flex items-center justify-center group-hover:bg-slate-200 transition">
                    <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-slate-700">Yıllık Faaliyet Raporu</h2>
                    <p class="mt-1 text-xs text-slate-500">Yıl bazında özet: tahsilat, gider, kasa</p>
                </div>
            </div>
        </a>

        {{-- Bütçe Raporu --}}
        <a href="{{ route('reports.budget') }}" class="group bg-white rounded-2xl border border-slate-200 p-5 hover:border-amber-400 hover:shadow-md transition-all">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center group-hover:bg-amber-100 transition">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 group-hover:text-amber-600">Bütçe Raporu</h2>
                    <p class="mt-1 text-xs text-slate-500">Kategori bazlı gider gerçekleşme analizi</p>
                </div>
            </div>
        </a>

    </div>
@endsection
