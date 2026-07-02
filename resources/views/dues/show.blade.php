@extends('layouts.app')



@section('content')

    {{-- Header --}}

    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">

        <div>

            <h1 class="text-2xl font-bold text-slate-950">Aidat Detayı <span class="text-slate-400 font-normal text-lg">— Aidatlar</span></h1>

            @if ($due->description)

                <p class="mt-1 text-base text-slate-500">{{ $due->description }}</p>

            @endif

        </div>

        <div class="flex gap-2">

            @if ($due->status !== 'paid')

                <a href="{{ route('dues.payment.create', $due) }}" class="flex-1 md:flex-none rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-emerald-700">Tahsil Et</a>

            @endif

            @if (! in_array($due->status, ['paid', 'partial']) && $transferableAccounts->isNotEmpty())

                <button type="button" onclick="document.getElementById('transfer-due-modal').classList.remove('hidden')"
                        class="flex-1 md:flex-none rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-orange-600">Borç Aktar</button>

            @endif

            <a href="{{ route('dues.edit', $due) }}" class="flex-1 md:flex-none rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 text-center hover:bg-slate-50">Düzenle</a>

            @if (in_array($due->status, ['paid', 'partial']))

                <button type="button" onclick="alert('Bu aidat ödenmiş olduğu için silinemez. Önce ilgili ödemeleri iptal edin.')" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>

            @else

                <form method="POST" action="{{ route('dues.destroy', $due) }}" onsubmit="return confirm('Aidat kaydı silinsin mi?')">

                    @csrf

                    @method('DELETE')

                    <button type="submit" class="flex-1 md:flex-none rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>

                </form>

            @endif

            <a href="{{ route('dues.index') }}" class="flex-1 md:flex-none rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white text-center hover:bg-slate-800">Aidatlara Dön</a>

        </div>

    </div>



    {{-- Info Card --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm mb-6">

        <div class="grid grid-cols-2 md:grid-cols-3 gap-6">

            @if ($due->account)

                <div>

                    <div class="text-xs text-slate-400 mb-1">HESAP</div>

                    @php

                        $title = match($due->account->type) {

                            App\Models\Account::TYPE_OWNER => 'Kat Maliki',

                            App\Models\Account::TYPE_TENANT => 'Kiracı',

                            App\Models\Account::TYPE_SUPPLIER => 'Tedarikçi',

                            default => ''

                        };

                    @endphp

                    <div class="text-sm font-medium text-slate-900">

                        @if ($title){{ $title }} @endif{{ $due->account->name }}

                        @if ($due->unit) - Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}@endif

                    </div>

                </div>

            @endif

            <div>

                <div class="text-xs text-slate-400 mb-1">TUTAR</div>

                <div class="text-sm font-bold text-slate-900">{{ number_format($due->amount, 2, ',', '.') }} TL</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">TÜR / KATEGORİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $due->due_type_label }}</div>
                @if ($due->category)
                    <div class="text-xs text-slate-500 mt-0.5">{{ $due->category->name }}</div>
                @endif

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">OLUŞTURULMA TARİHİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">SON ÖDEME TARİHİ</div>

                <div class="text-sm font-medium text-slate-900">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</div>

            </div>

            <div>

                <div class="text-xs text-slate-400 mb-1">DURUM</div>

                @php

                    $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();

                    if ($isOverdue) {

                        $statusInfo = ['label' => 'Gecikmiş', 'class' => 'bg-red-50 text-red-600 border border-red-200'];

                    } elseif ($due->status === 'paid') {

                        $statusInfo = ['label' => 'Ödendi', 'class' => 'bg-emerald-50 text-emerald-600 border border-emerald-200'];

                    } elseif ($due->status === 'partial') {

                        $statusInfo = ['label' => 'Kısmen Ödendi', 'class' => 'bg-amber-50 text-amber-600 border border-amber-200'];

                    } else {

                        $statusInfo = ['label' => 'Bekliyor', 'class' => 'bg-slate-50 text-slate-600 border border-slate-200'];

                    }

                @endphp

                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $statusInfo['class'] }}">

                    {{ $statusInfo['label'] }}

                </span>

            </div>

            @if ($due->reference_number)

                <div>

                    <div class="text-xs text-slate-400 mb-1">REFERANS</div>

                    <div class="text-sm font-medium text-slate-900">{{ $due->reference_number }}</div>

                </div>

            @endif

            <div>

                <div class="text-xs text-slate-400 mb-1">KAYNAK</div>

                @if ($due->batch?->plan)

                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 border border-violet-200">

                        Aidat Planı: {{ $due->batch->plan->name }}

                    </span>

                @elseif ($due->batch)

                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200">

                        Toplu Borçlandırma

                    </span>

                @else

                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200">

                        Manuel

                    </span>

                @endif

            </div>

        </div>

    </div>



    {{-- Payment Allocations Section --}}

    <div class="rounded-2xl bg-white p-6 shadow-sm">

        <h2 class="text-lg font-semibold text-slate-950 mb-4">Borcu Kapatan Tahsilat</h2>

        @if ($due->allocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu borca henüz tahsis edilmiş ödeme yok.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="min-w-full divide-y divide-slate-200 text-sm">

                    <thead class="bg-slate-50 text-left text-slate-500">

                        <tr>

                            <th class="px-5 py-3">Ref No</th>

                            <th class="px-5 py-3">Açıklama</th>

                            <th class="px-5 py-3 text-right">Tutar</th>

                            <th class="px-5 py-3 text-right">Kapatılan</th>

                            <th class="px-5 py-3">Ödeme Tarihi</th>

                            <th class="px-5 py-3"></th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @foreach ($due->allocations as $allocation)

                            <tr>

                                <td class="px-5 py-4">

                                    <a href="{{ route('payments.show', $allocation->payment) }}" class="font-medium text-slate-900 hover:text-emerald-600">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }}</a>

                                </td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->description ?: 'Ödeme' }}</td>

                                <td class="px-5 py-4 text-right font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->payment->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-right font-semibold text-emerald-600 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</td>

                                <td class="px-5 py-4 text-slate-700">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</td>

                                <td class="px-5 py-4 text-right">

                                    <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.remove('hidden')"
                                            class="rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            @foreach ($due->allocations as $allocation)
                @php
                    $hasMultipleAllocations = $allocation->payment->allocations_count > 1;
                @endphp

                <div id="revert-allocation-modal-{{ $allocation->id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900 mb-1">Tahsisatı Geri Al</h3>
                        <p class="text-sm text-slate-500 mb-4">
                            {{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }} —
                            {{ number_format($allocation->payment->amount, 2, ',', '.') }} TL
                        </p>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-4">
                            <div class="text-xs text-slate-500 mb-1">BU AİDATA TAHŞİS EDİLEN</div>
                            <div class="text-sm font-semibold text-slate-900">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <form method="POST" action="{{ route('payments.allocations.destroy', [$allocation->payment, $allocation]) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_to" value="{{ route('dues.show', $due) }}">
                                <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                    Sadece Geri Al (Tahsilat Hesapta Kalır)
                                </button>
                            </form>

                            <form method="POST" action="{{ route('payments.destroy', $allocation->payment) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_to" value="{{ route('dues.show', $due) }}">
                                <button type="submit" @disabled($hasMultipleAllocations)
                                        class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold {{ $hasMultipleAllocations ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700' }}">
                                    Tahsilatı da Sil
                                </button>
                            </form>

                            @if ($hasMultipleAllocations)
                                <p class="text-xs text-amber-600">Bu tahsilat başka aidatlara/giderlere de tahsis edilmiş; sadece geri alabilirsiniz.</p>
                            @endif

                            <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.add('hidden')"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Vazgeç
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                    document.getElementById('revert-allocation-modal-{{ $allocation->id }}')?.addEventListener('click', function(e) {
                        if (e.target === this) this.classList.add('hidden');
                    });
                </script>
            @endforeach

        @endif

    </div>



    @if (! in_array($due->status, ['paid', 'partial']) && $transferableAccounts->isNotEmpty())

    <div id="transfer-due-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">

        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">

            <h3 class="text-lg font-semibold text-slate-900 mb-1">Borç Aktar</h3>

            <p class="text-sm text-slate-500 mb-4">Bu açık aidatı başka bir hesaba devredin.</p>



            <form method="POST" action="{{ route('dues.transfer', $due) }}" class="space-y-4">

                @csrf



                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">Hedef Hesap</label>

                    <select name="target_account_id" required
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">

                        <option value="">— Hesap seçin —</option>

                        @foreach ($transferableAccounts as $ta)

                            @php
                                $taType = match($ta->type) {
                                    App\Models\Account::TYPE_OWNER  => 'Kat Maliki',
                                    App\Models\Account::TYPE_TENANT => 'Kiracı',
                                    default => '',
                                };
                            @endphp

                            <option value="{{ $ta->id }}">{{ $ta->name }} @if($taType)({{ $taType }})@endif</option>

                        @endforeach

                    </select>

                </div>



                <div class="flex gap-3 pt-1">

                    <button type="submit" class="flex-1 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Devret</button>

                    <button type="button" onclick="document.getElementById('transfer-due-modal').classList.add('hidden')"
                            class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>

                </div>

            </form>

        </div>

    </div>



    <script>
        document.getElementById('transfer-due-modal')?.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    </script>

    @endif

@endsection

