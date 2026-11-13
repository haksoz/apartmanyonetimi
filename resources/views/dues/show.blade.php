@extends('layouts.app')

@section('content_spacing', 'py-3 sm:py-4')



@section('content')

    @php
        $dueTitle = $due->description ?: 'Aidat';
    @endphp

    {{-- Header --}}
    <div class="mb-3 flex flex-row items-center justify-between gap-2 min-w-0 sm:gap-3">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-950">
                Aidat
            </h1>
        </div>

        <div class="flex shrink-0 items-center justify-end gap-1 sm:gap-2">
            <div class="flex items-center gap-1 sm:gap-2">
                @if ($due->status !== 'paid')
                    <button type="button" data-due-id="{{ $due->id }}" data-amount="{{ $due->remaining_amount }}" data-description="{{ $dueTitle }}" data-account="{{ $due->account?->name }}" data-unit="{{ $due->unit?->unit_no ? str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) : '-' }}" onclick="openDuePaymentModal(Number(this.dataset.dueId), Number(this.dataset.amount), this.dataset.description, this.dataset.account, this.dataset.unit)" class="inline-flex h-11 w-16 items-center justify-center text-center sm:h-auto sm:w-auto rounded-xl bg-emerald-600 px-1 py-2 text-xs sm:px-4 sm:py-2.5 sm:text-sm font-semibold text-white hover:bg-emerald-700">Tahsil Et</button>
                @endif



                <details class="group relative">
                    <summary class="inline-flex h-12 cursor-pointer list-none items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300">
                        İşlemler
                        <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </summary>
                    <div class="absolute right-0 top-full z-20 mt-2 flex w-40 flex-col gap-2 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
                <a href="{{ route('dues.edit', $due) }}" class="flex w-full items-center justify-start text-left rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Düzenle</a>

                @if (! in_array($due->status, ['paid', 'partial']) && $transferableAccounts->isNotEmpty())
                    <button type="button" onclick="this.closest('details').open = false; document.getElementById('transfer-due-modal').classList.remove('hidden')" class="flex w-full items-center justify-start rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5 text-left text-sm font-semibold text-sky-700 hover:bg-sky-100">Borç Aktar</button>
                @endif

                @if ($due->allocations->isNotEmpty())
                    <button type="button" onclick="document.getElementById('delete-due-modal').classList.remove('hidden')" class="flex w-full items-center justify-start text-left rounded-xl border border-red-200 px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                @else
                    <form method="POST" action="{{ route('dues.destroy', $due) }}" onsubmit="return confirm('Aidat kaydı silinsin mi?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex w-full items-center justify-start text-left rounded-xl border border-red-200 px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Sil</button>
                    </form>
                @endif
                    </div>
                </details>
            </div>



            <button type="button" onclick="history.back()" class="inline-flex h-11 w-16 shrink-0 items-center justify-center sm:h-auto sm:w-auto gap-1 whitespace-nowrap rounded-xl border border-slate-300 px-1 py-2 sm:gap-2 sm:py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h14" />
                </svg>
                Geri
            </button>
        </div>
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

            <div class="mb-4">
                <div class="text-xl font-bold text-slate-900">{{ $dueTitle }}</div>
            </div>

            <div class="mb-5 grid grid-cols-3 gap-1 rounded-2xl bg-slate-100 p-1 text-center ring-1 ring-inset ring-slate-200/60 sm:gap-3 sm:rounded-none sm:bg-transparent sm:p-0 sm:text-left sm:ring-0">
                <div class="min-w-0 rounded-xl bg-white px-1 py-3 shadow-sm sm:border sm:border-slate-200 sm:bg-slate-50 sm:p-4 sm:shadow-none">
                    <div class="text-xs font-medium text-slate-500 sm:text-sm sm:text-slate-600">Tutar</div>
                    <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight text-slate-900 tabular-nums sm:mt-2 sm:text-2xl">{{ number_format($due->amount, 2, ',', '.') }} TL</div>
                </div>
                <div class="min-w-0 rounded-xl bg-emerald-50 px-1 py-3 sm:border sm:border-emerald-200 sm:p-4">
                    <div class="text-xs font-medium text-emerald-700 sm:text-sm">Ödenen</div>
                    <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight text-emerald-700 tabular-nums sm:mt-2 sm:text-2xl">{{ number_format($paidAmount, 2, ',', '.') }} TL</div>
                </div>
                <div class="min-w-0 rounded-xl px-1 py-3 sm:border sm:p-4 {{ $due->remaining_amount > 0 ? 'bg-amber-50 sm:border-amber-200' : 'bg-white sm:border-slate-200 sm:bg-slate-50' }}">
                    <div class="text-xs font-medium text-slate-500 sm:text-sm sm:text-slate-600">Kalan</div>
                    <div class="mt-1 whitespace-nowrap text-[clamp(0.65rem,3.5vw,1rem)] font-bold leading-tight {{ $due->remaining_amount > 0 ? 'text-amber-700' : 'text-slate-900' }} tabular-nums sm:mt-2 sm:text-2xl">{{ number_format($due->remaining_amount, 2, ',', '.') }} TL</div>
                </div>
            </div>

                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 text-sm">
                    @if ($due->account)
                        <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                            <div class="text-xs text-slate-500">Hesap</div>
                            <div class="font-semibold text-slate-900 text-right">
                                <a href="{{ route('accounts.show', $due->account) }}" class="hover:text-slate-600">{{ $due->account->name }}</a>
                                @if ($due->unit)
                                    <span class="text-slate-500"> · Daire {{ str_pad($due->unit->unit_no, 2, '0', STR_PAD_LEFT) }}</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                        <div class="text-xs text-slate-500">Borç Türü</div>
                        <div class="text-right font-semibold text-slate-900">
                            {{ $due->due_type_label }}
                            <span class="text-xs font-normal text-slate-500">({{ $due->targeting_rule_label }})</span>
                        </div>
                    </div>

                    @if ($due->category)
                        <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                            <div class="text-xs text-slate-500">Kategori</div>
                            <div class="font-semibold text-slate-900">{{ $due->category->name }}</div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between gap-4 bg-white px-4 py-3">
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <div class="text-xs text-slate-500">Oluşturulma</div>
                            <div class="font-semibold text-slate-900">{{ $due->created_at_manual ? \Carbon\Carbon::parse($due->created_at_manual)->format('d.m.Y') : $due->created_at->format('d.m.Y') }}</div>
                        </div>
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <div class="text-xs text-slate-500">Son Ödeme</div>
                            <div class="font-semibold text-slate-900">{{ $due->due_date?->format('d.m.Y') ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                        <div class="text-xs text-slate-500">Durum</div>
                        <div class="font-semibold text-slate-900">
                            <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-semibold {{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                        <div class="text-xs text-slate-500">Kaynak</div>
                        <div class="font-semibold text-slate-900 text-right">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-200">{{ $due->source_label }}</span>
                        </div>
                    </div>
                    @if ($due->reference_number)
                        <div class="flex items-center justify-between gap-3 bg-white px-4 py-3">
                            <div class="text-xs text-slate-500">Referans</div>
                            <div class="font-semibold text-slate-900">{{ $due->reference_number }}</div>
                        </div>
                    @endif
                </div>

        </div>

    </div>

    {{-- Payment Allocations Section --}}

    <div class="rounded-2xl bg-white p-4 md:p-6 shadow-sm mb-6">

        <h2 class="text-lg font-semibold text-slate-950 mb-4">Aidat İçin Yapılan Tahsilatlar</h2>

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

    @include('dues._payment_modal', ['cashBoxes' => $cashBoxes])
    @include('dues._payment_modal_script')
@endsection

