@extends('layouts.app')

@section('title', 'Tanımlamalar')

@section('content')
    <div class="max-w-4xl">
        <h1 class="text-2xl font-bold text-slate-950">Tanımlamalar</h1>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PATCH')
            <h2 class="text-lg font-semibold text-slate-900">Test verileri</h2>
            <input type="hidden" name="show_test_customers" value="0">
            <label class="mt-4 flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" name="show_test_customers" value="1" @checked(old('show_test_customers', $showTestCustomers))>
                Test müşterilerini göster
            </label>
            <p class="mt-2 text-sm text-slate-500">Tüm yöneticiler için müşteri, apartman, abonelik, sipariş ve işlem geçmişi listelerine test kayıtlarını dahil eder. Kayıtlar silinmez. PayTR ödeme modu bu ayardan bağımsızdır.</p>
            @error('show_test_customers')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="mt-4 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Kaydet</button>
        </form>
    </div>
@endsection
