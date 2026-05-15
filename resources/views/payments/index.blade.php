@extends('layouts.app')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Tahsilatlar</h1>
            <p class="mt-1 text-sm text-slate-500">Hesaplardan alınan tahsilatlar ve tahsis durumları.</p>
        </div>
        <a href="{{ route('accounts.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Hesaplara Dön</a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-5 py-3">Ref No</th>
                    <th class="px-5 py-3">Hesap</th>
                    <th class="px-5 py-3">Açıklama</th>
                    <th class="px-5 py-3">Tarih</th>
                    <th class="px-5 py-3 text-right">Tutar</th>
                    <th class="px-5 py-3 text-right">Dağıtılmamış</th>
                    <th class="px-5 py-3 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-5 py-4 text-slate-700">{{ $payment->reference_number ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $payment->account?->name ?? '-' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $payment->description ?: 'Ödeme' }}</td>
                        <td class="px-5 py-4 text-slate-700">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</td>
                        <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($payment->amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right text-slate-900 font-semibold">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</td>
                        <td class="px-5 py-4 text-right space-x-2">
                            <a href="{{ route('payments.show', $payment) }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detay</a>
                            @if ($payment->unallocated_amount > 0)
                                <a href="{{ route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-6 text-sm text-slate-500">Henüz kayıtlı tahsilat bulunamadı.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
