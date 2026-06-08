@extends('layouts.app')

@section('content')
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            @if($isOrphanFilter ?? false)
                <h1 class="text-2xl font-bold text-amber-700">Hesapsız Ödemeler</h1>
                <p class="mt-1 text-sm text-amber-600">Tahsis edilmemiş, hesaba bağlı olmayan ödemeler.</p>
            @else
                <h1 class="text-2xl font-bold text-slate-950">Ödeme Hareketleri</h1>
                <p class="mt-1 text-sm text-slate-500">Hesaplardan alınan tahsilatlar ve yapılan ödemeler.</p>
            @endif
        </div>
        <div class="flex gap-2">
            @if($isOrphanFilter ?? false)
                <a href="{{ route('accounts.index') }}" class="flex-1 md:flex-none rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-amber-700">← Hesaplara Dön</a>
                <a href="{{ route('payments.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Tüm Ödemeler</a>
            @else
                <a href="{{ route('accounts.index') }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Hesaplara Dön</a>
            @endif
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-hidden rounded-2xl bg-white shadow-sm">
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
                                @php $isSupplierPayment = $payment->account && $payment->account->type === App\Models\Account::TYPE_SUPPLIER; @endphp
                                <a href="{{ $isSupplierPayment ? route('payments.supplier-allocations.create', $payment) : route('payments.allocations.create', $payment) }}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsis Et</a>
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

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse ($payments as $payment)
            <div class="rounded-xl bg-white p-4 shadow-sm border border-slate-200">
                {{-- Header: Ref No & Account --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="text-lg font-bold text-slate-900">#{{ $payment->reference_number ?? '-' }}</div>
                        <div class="text-sm text-slate-600">{{ $payment->account?->name ?? '-' }}</div>
                    </div>
                    @if ($payment->unallocated_amount > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            Tahsis Bekliyor
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            Tamamlandı
                        </span>
                    @endif
                </div>

                {{-- Info Grid --}}
                <div class="grid grid-cols-2 gap-3 mb-3 text-sm">
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                        <div class="font-medium text-slate-900">{{ $payment->description ?: 'Ödeme' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 mb-1">Tarih</div>
                        <div class="font-medium text-slate-900">{{ $payment->payment_date?->format('d.m.Y') ?? '-' }}</div>
                    </div>
                </div>

                {{-- Amount Section --}}
                <div class="bg-slate-50 rounded-lg p-3 mb-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-xs text-slate-500 mb-1">Tutar</div>
                            <div class="text-lg font-bold text-slate-900">{{ number_format($payment->amount, 2, ',', '.') }} TL</div>
                        </div>
                        @if ($payment->unallocated_amount > 0)
                            <div class="text-right">
                                <div class="text-xs text-slate-500 mb-1">Dağıtılmamış</div>
                                <div class="text-base font-semibold text-amber-600">{{ number_format($payment->unallocated_amount, 2, ',', '.') }} TL</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex gap-2">
                    <a href="{{ route('payments.show', $payment) }}" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Detay</a>
                    @if ($payment->unallocated_amount > 0)
                        @php $isSupplierPayment = $payment->account && $payment->account->type === App\Models\Account::TYPE_SUPPLIER; @endphp
                        <a href="{{ $isSupplierPayment ? route('payments.supplier-allocations.create', $payment) : route('payments.allocations.create', $payment) }}" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white text-center hover:bg-emerald-700">Tahsis Et</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm">
                Henüz kayıtlı tahsilat bulunamadı.
            </div>
        @endforelse
    </div>
@endsection
