@extends('layouts.app')



@section('content')

    {{-- Header --}}

    <div class="mb-6 flex flex-row items-center justify-between gap-4">

        <div>

            <h1 class="text-xl font-bold text-slate-950 lg:text-2xl">Aidat Detayı</h1>

            @if ($due->reference_number)
                <div class="mt-1">
                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-600">Referans: {{ $due->reference_number }}</span>
                </div>
            @endif

            @if ($due->account)
                <div class="mt-1">
                    <a href="{{ route('accounts.show', $due->account) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-3 py-1 text-sm font-semibold text-slate-600 hover:text-emerald-600 hover:bg-slate-100">
                        {{ $due->account->name }}
                        @if ($due->unit) - Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}@endif
                    </a>
                </div>
            @endif

        </div>

        {{-- Masaüstü butonlar --}}
        <div class="hidden lg:flex flex-wrap gap-2">

            @if ($due->status !== 'paid')

                <a href="{{ route('dues.payment.create', $due) }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Tahsil Et</a>

            @endif

            @if (! in_array($due->status, ['paid', 'partial']) && $transferableAccounts->isNotEmpty())

                <button type="button" onclick="document.getElementById('transfer-due-modal').classList.remove('hidden')"
                        class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600">Borç Aktar</button>

            @endif

            <a href="{{ route('dues.edit', $due) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600">Düzenle</a>

            @if ($due->allocations->isNotEmpty())

                <button type="button" onclick="document.getElementById('delete-due-modal').classList.remove('hidden')" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Sil</button>

            @else

                <form method="POST" action="{{ route('dues.destroy', $due) }}" onsubmit="return confirm('Aidat kaydı silinsin mi?')">

                    @csrf

                    @method('DELETE')

                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Sil</button>

                </form>

            @endif

            <a href="{{ route('dues.index') }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Aidatlara Dön</a>

        </div>

        {{-- Mobil işlemler menüsü --}}
        <details class="lg:hidden relative group">
            <summary class="cursor-pointer list-none rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 flex items-center justify-end gap-2 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
                İşlem
            </summary>
            <div class="absolute right-0 top-full mt-2 w-56 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-100 flex flex-col gap-2 z-20">

                @if ($due->status !== 'paid')

                    <a href="{{ route('dues.payment.create', $due) }}" class="block w-full rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-emerald-700">Tahsil Et</a>

                @endif

                @if (! in_array($due->status, ['paid', 'partial']) && $transferableAccounts->isNotEmpty())

                    <button type="button" onclick="document.getElementById('transfer-due-modal').classList.remove('hidden')"
                            class="block w-full rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-orange-600">Borç Aktar</button>

                @endif

                <a href="{{ route('dues.edit', $due) }}" class="block w-full rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-amber-600">Düzenle</a>

                @if ($due->allocations->isNotEmpty())

                    <button type="button" onclick="document.getElementById('delete-due-modal').classList.remove('hidden')" class="block w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-red-700">Sil</button>

                @else

                    <form method="POST" action="{{ route('dues.destroy', $due) }}" onsubmit="return confirm('Aidat kaydı silinsin mi?')">

                        @csrf

                        @method('DELETE')

                        <button type="submit" class="block w-full rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-red-700">Sil</button>

                    </form>

                @endif

                <a href="{{ route('dues.index') }}" class="block w-full rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white text-left hover:bg-slate-800">Aidatlara Dön</a>

            </div>
        </details>

    </div>



    @php
        $paidAmount = $due->amount - $due->remaining_amount;
        $isOverdue = $due->status !== 'paid' && $due->due_date && $due->due_date->isPast();
        $statusInfo = match(true) {
            $due->status === 'paid' => ['label' => 'Ödendi', 'class' => 'bg-emerald-50 text-emerald-600 border border-emerald-200'],
            $due->status === 'partial' => ['label' => 'Kısmen Ödendi', 'class' => 'bg-amber-50 text-amber-600 border border-amber-200'],
            $isOverdue => ['label' => 'Gecikmiş', 'class' => 'bg-red-50 text-red-600 border border-red-200'],
            default => ['label' => 'Bekliyor', 'class' => 'bg-slate-50 text-slate-600 border border-slate-200'],
        };
    @endphp

    {{-- Info Card --}}

    <div class="rounded-2xl bg-white shadow-sm mb-6 overflow-hidden">

        <div class="p-4 md:p-6 border-b border-slate-100">

            <div class="mb-5">
                <div class="text-xs text-slate-500 mb-1">Açıklama</div>
                <div class="text-2xl font-bold text-slate-900">{{ $due->description ?: 'Aidat' }}</div>
            </div>

            <details class="group">
                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-slate-900 focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Detaylar
                </summary>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm">

                    @if ($due->account)
                        <div class="flex items-center justify-between gap-2 md:col-span-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Hesap</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">
                                <a href="{{ route('accounts.show', $due->account) }}" class="hover:text-emerald-600 hover:underline">{{ $due->account->name }}</a>
                                @if ($due->unit) - Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}@endif
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Tür / Kategori</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">
                            {{ $due->due_type_label }}
                            @if ($due->category)
                                <span class="text-xs text-slate-500 block">{{ $due->category->name }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Oluşturulma</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Son Ödeme</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Durum</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">
                            <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                        </div>
                    </div>

                    @if ($due->reference_number)
                        <div class="flex items-center justify-between gap-2 md:justify-start">
                            <div class="text-xs text-slate-500 md:w-24 shrink-0">Referans</div>
                            <div class="font-semibold text-slate-900 text-right md:text-left">{{ $due->reference_number }}</div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-2 md:justify-start">
                        <div class="text-xs text-slate-500 md:w-24 shrink-0">Kaynak</div>
                        <div class="font-semibold text-slate-900 text-right md:text-left">
                            @if ($due->batch?->plan)
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 border border-violet-200">Aidat Planı: {{ $due->batch->plan->name }}</span>
                            @elseif ($due->batch)
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200">Toplu Borçlandırma</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200">Manuel</span>
                            @endif
                        </div>
                    </div>

                </div>
            </details>

        </div>

        {{-- Alt: Tutar / Ödenen / Kalan --}}
        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Tutar</div>
                <div class="text-lg md:text-xl font-bold text-slate-900 tabular-nums">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Ödenen</div>
                <div class="text-lg md:text-xl font-bold text-emerald-600 tabular-nums">{{ number_format($paidAmount, 2, ',', '.') }} TL</div>
            </div>
            <div class="py-3 px-4 md:p-5 flex items-center justify-between gap-1">
                <div class="text-[10px] md:text-xs font-semibold uppercase tracking-wide text-slate-400">Kalan</div>
                <div class="text-lg md:text-xl font-bold {{ $due->remaining_amount > 0 ? 'text-amber-600' : 'text-slate-400' }} tabular-nums">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
            </div>
        </div>

    </div>



    {{-- Payment Allocations Section --}}

    <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

        <h2 class="text-lg font-semibold text-slate-950 mb-4">Aidatı Kapatan Tahsilat / Tahsilatlar</h2>

        @if ($due->allocations->isEmpty())

            <div class="py-6 text-sm text-slate-500">Bu borca henüz tahsis edilmiş ödeme yok.</div>

        @else

            <div class="overflow-hidden rounded-xl border border-slate-200">

                <table class="hidden md:table min-w-full divide-y divide-slate-200 text-sm">

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

                {{-- Mobil: Kapatan Tahsilatlar Kartları --}}
                <div class="md:hidden divide-y divide-slate-100">
                    @foreach ($due->allocations as $allocation)
                        <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('payments.show', $allocation->payment) }}" class="block">
                                    <div class="text-sm text-slate-700 truncate">
                                        {{ $allocation->payment->description ?: 'Tahsilat' }}
                                    </div>
                                    <div class="mt-0.5 flex items-center justify-between gap-2">
                                        <span class="text-xs text-slate-500">{{ $allocation->payment->payment_date?->format('d.m.Y') ?? '-' }}</span>
                                        <span class="text-sm font-semibold text-emerald-600">{{ number_format($allocation->amount, 2, ',', '.') }} TL</span>
                                    </div>
                                </a>
                            </div>
                            <button type="button" onclick="document.getElementById('revert-allocation-modal-{{ $allocation->id }}').classList.remove('hidden')"
                                    class="shrink-0 rounded-lg border border-red-200 px-3 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Geri Al</button>
                        </div>
                    @endforeach
                </div>

            </div>

            @foreach ($due->allocations as $allocation)
                @php
                    $canDeletePayment = $allocation->payment->allocations_count === 1
                        && round((float) $allocation->payment->amount, 2) === round((float) $allocation->amount, 2);
                @endphp

                <div id="revert-allocation-modal-{{ $allocation->id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                    <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                        <h3 class="text-lg font-semibold text-slate-900 mb-1">Aidat Kapamayı Geri Al</h3>
                        <p class="text-sm text-slate-500 mb-4">
                            {{ $allocation->payment->reference_number ?? '#'.$allocation->payment->id }} —
                            {{ number_format($allocation->payment->amount, 2, ',', '.') }} TL
                        </p>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mb-4">
                            <div class="text-xs text-slate-500 mb-1">BU AİDATA TAHŞİS EDİLEN</div>
                            <div class="text-sm font-semibold text-slate-900">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                        </div>

                        <div class="flex flex-col gap-3">
                            @unless ($canDeletePayment)
                                <form method="POST" action="{{ route('payments.allocations.destroy', [$allocation->payment, $allocation]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_to" value="{{ route('dues.show', $due) }}">
                                    <button type="submit" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">
                                        Geri Al (Tahsilat Hesapta Kalır)
                                    </button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('payments.destroy', $allocation->payment) }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="redirect_to" value="{{ route('dues.show', $due) }}">
                                <button type="submit" @disabled(! $canDeletePayment)
                                        class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold {{ $canDeletePayment ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-slate-100 text-slate-400 cursor-not-allowed' }}">
                                    Tahsilatı Sil
                                </button>
                            </form>

                            @unless ($canDeletePayment)
                                <p class="text-xs text-amber-600">Bu tahsilat bu aidatı birebir kapatmadığından silinemez; yalnızca aidat kapamayı geri alabilirsiniz.</p>
                            @endunless

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

    @if ($due->allocations->isNotEmpty())
        <div id="delete-due-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">Aidatı Sil</h3>
                <p class="text-sm text-slate-500 mb-4">Bu aidatı kapatan tahsilatlar:</p>

                <div class="overflow-hidden rounded-xl border border-slate-200 mb-4">
                    <div class="divide-y divide-slate-100">
                        @foreach ($due->allocations as $allocation)
                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900">{{ $allocation->payment->reference_number ?? '#'.$allocation->payment_id }}</div>
                                    <div class="truncate text-slate-500">{{ $allocation->payment->description ?: 'Tahsilat' }}</div>
                                </div>
                                <div class="shrink-0 font-semibold text-slate-900 tabular-nums">{{ number_format($allocation->amount, 2, ',', '.') }} TL</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 mb-4">
                    Bu aidatı kapatan tahsilatlar olduğu için aidat silinemez.
                </div>

                <button type="button" onclick="document.getElementById('delete-due-modal').classList.add('hidden')" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Vazgeç</button>
            </div>
        </div>

        <script>
            document.getElementById('delete-due-modal')?.addEventListener('click', function(e) {
                if (e.target === this) this.classList.add('hidden');
            });
        </script>
    @endif

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

