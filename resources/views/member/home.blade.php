@extends('layouts.app')

@section('title', $apartment->name)

@section('content')
    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Dairem</p>
        <h1 class="mt-1 text-xl font-bold text-slate-950 sm:text-2xl">{{ $apartment->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Dairenizin borç, alacak ve aidat durumu.</p>
    </div>

    @if ($accounts->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-8 text-sm text-slate-500 shadow-sm">Bu binada size bağlı bir daire hesabı yok.</div>
    @endif

    <div class="space-y-4">
        @foreach ($accounts as $account)
            @php
                $balance = (float) $account->status_balance;
                $openDues = $account->dues->where('is_imported', false);
                $importedDues = $account->dues->where('is_imported', true);
                $openPayments = $account->payments->where('is_imported', false);
                $importedPayments = $account->payments->where('is_imported', true);
                $moveIn = $account->type === \App\Models\Account::TYPE_TENANT && $account->activeTenantAssignment
                    ? $account->activeTenantAssignment->move_in_date
                    : $account->account_opening_date;
            @endphp
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
                <div class="border-b border-slate-100 p-4 md:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-slate-950">
                            @if ($account->unit)
                                Daire {{ str_pad($account->unit->unit_no, 2, '0', STR_PAD_LEFT) }} - {{ $account->name }}
                            @else
                                {{ $account->name }}
                            @endif
                        </h2>
                        @unless ($account->is_active)
                            <span class="inline-flex rounded-lg bg-red-50 px-3 py-1 text-sm font-semibold text-red-600">Pasif</span>
                        @endunless
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $account->type_label }}</p>

                    <details class="group mt-4">
                        <summary class="cursor-pointer list-none text-sm font-semibold text-slate-600">Detaylar</summary>
                        <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                            @if ($account->unit?->floor)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Kat</span><span class="font-semibold">{{ $account->unit->floor }}</span></div>
                            @endif
                            @if ($account->unit?->block)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Blok</span><span class="font-semibold">{{ $account->unit->block }}</span></div>
                            @endif
                            @if ($account->unit?->square_meters)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Alan</span><span class="font-semibold">{{ number_format($account->unit->square_meters, 0, ',', '.') }} m²</span></div>
                            @endif
                            @if ($account->unit?->share_coefficient)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Hisse Katsayısı</span><span class="font-semibold">{{ $account->unit->share_coefficient }}</span></div>
                            @endif
                            @if ($account->phone)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">Telefon</span><span class="font-semibold">{{ $account->phone }}</span></div>
                            @endif
                            @if ($account->email)
                                <div class="flex items-center justify-between gap-2"><span class="text-slate-500">E-posta</span><span class="font-semibold">{{ $account->email }}</span></div>
                            @endif
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-500">{{ $account->type === \App\Models\Account::TYPE_TENANT ? 'Kiracı Girişi' : 'Hesap Açılışı' }}</span>
                                <span class="font-semibold">{{ $moveIn ? $moveIn->format('d.m.Y') : '—' }}</span>
                            </div>
                            @if ($account->account_end_date)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-slate-500">{{ $account->type === \App\Models\Account::TYPE_TENANT ? 'Kiracı Çıkışı' : 'Maliklik Bitişi' }}</span>
                                    <span class="font-semibold text-red-600">{{ $account->account_end_date->format('d.m.Y') }}</span>
                                </div>
                            @endif
                        </div>
                    </details>
                </div>

                <div class="grid divide-y divide-slate-100 md:grid-cols-3 md:divide-x md:divide-y-0">
                    <div class="flex items-center justify-between px-4 py-3 md:p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Borç</div>
                        <div class="text-lg font-bold text-red-600">{{ number_format((float) $account->status_debit, 2, ',', '.') }} TL</div>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 md:p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Alacak</div>
                        <div class="text-lg font-bold text-emerald-600">{{ number_format((float) $account->status_credit, 2, ',', '.') }} TL</div>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 md:p-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Bakiye</div>
                        <div class="text-lg font-bold {{ $balance < 0 ? 'text-red-600' : ($balance > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                            {{ number_format(abs($balance), 2, ',', '.') }} TL
                            @if ($balance != 0)
                                <span class="text-xs font-normal">{{ $balance < 0 ? '(B)' : '(A)' }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-4 md:px-6">
                    <button type="button" disabled class="cursor-not-allowed rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-500">Kartla öde</button>
                    <a href="{{ route('reports.account-statement', ['account_id' => $account->id, 'date_from' => now()->startOfYear()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cari ekstre</a>
                </div>
                <p class="px-4 pb-4 text-xs text-slate-400 md:px-6">Kartla ödeme henüz açılmadı.</p>
            </section>

            <section class="rounded-2xl bg-white p-4 shadow-sm md:p-6">
                <h3 class="text-lg font-semibold text-slate-950">Açık Aidatlar</h3>
                @if ($openDues->isEmpty() && $importedDues->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">Bu hesap için ödenmemiş aidat yok.</p>
                @else
                    <div class="mt-4 space-y-3">
                        @foreach ($openDues->concat($importedDues) as $due)
                            @php
                                $createdOn = $due->created_at_manual
                                    ? \Carbon\Carbon::parse($due->created_at_manual)
                                    : $due->created_at;
                                $source = $due->batch?->plan ? 'Aidat Planı ile' : ($due->batch ? 'Toplu Borçlandırma' : 'Manuel');
                            @endphp
                            <article class="rounded-xl border border-slate-200 p-4">
                                <div class="font-medium text-slate-900">
                                    {{ $due->description ?: 'Aidat' }}
                                    @if ($due->is_imported)
                                        <span class="ml-1 inline-block rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                    @endif
                                </div>
                                <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Oluşturulma</div>
                                        <div class="mt-1 text-slate-700">{{ $createdOn?->format('d.m.Y') ?? '—' }}</div>
                                        <div class="text-xs text-slate-500">{{ $source }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Son Ödeme</div>
                                        <div class="mt-1 {{ $due->computed_status === 'overdue' ? 'font-semibold text-red-600' : 'text-slate-700' }}">{{ $due->due_date?->format('d.m.Y') ?? '—' }}</div>
                                        <div class="text-xs text-slate-500">{{ $due->due_type_label }}{{ $due->category ? ' · '.$due->category->name : '' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tutar</div>
                                        <div class="mt-1 font-semibold text-slate-900">{{ number_format((float) $due->amount, 2, ',', '.') }} TL</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Kalan</div>
                                        <div class="mt-1 font-semibold {{ $due->remaining_amount != $due->amount ? 'text-amber-600' : 'text-slate-700' }}">{{ number_format((float) $due->remaining_amount, 2, ',', '.') }} TL</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Durum</div>
                                        <div class="mt-1">
                                            @if ($due->computed_status === 'overdue')
                                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Gecikti</span>
                                            @elseif ($due->computed_status === 'partial')
                                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Kısmi</span>
                                            @elseif ($due->computed_status === 'paid')
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Ödendi</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Bekliyor</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($openPayments->isNotEmpty() || $importedPayments->isNotEmpty())
                <section class="rounded-2xl bg-white p-4 shadow-sm md:p-6">
                    <h3 class="text-lg font-semibold text-slate-950">Açık Tahsilatlar</h3>
                    <p class="mt-1 text-sm text-slate-500">Aidata henüz bağlanmamış ödemeler.</p>
                    <div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        @foreach ($openPayments->concat($importedPayments) as $payment)
                            <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <div class="text-xs text-slate-500">{{ $payment->payment_date?->format('d.m.Y') ?? '—' }}</div>
                                    <div class="text-slate-700">
                                        {{ $payment->description ?: 'Tahsilat' }}
                                        @if ($payment->is_imported)
                                            <span class="ml-1 rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">Devir Öncesi</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="font-semibold text-emerald-700">{{ number_format((float) $payment->unallocated_amount, 2, ',', '.') }} TL</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-2xl bg-white p-4 shadow-sm md:p-6">
                <h3 class="text-lg font-semibold text-slate-950">Son Hareketler</h3>
                @if ($account->recentTransactions->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">Henüz hareket yok.</p>
                @else
                    <div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        @foreach ($account->recentTransactions as $transaction)
                            <div class="grid grid-cols-2 gap-2 px-4 py-3 text-sm md:grid-cols-5">
                                <div class="text-slate-500">{{ $transaction->transaction_date?->format('d.m.Y') ?? '—' }}</div>
                                <div class="text-slate-700 md:col-span-1">{{ $transaction->description ?: ($transaction->type === 'debit' ? 'Borç' : 'Alacak') }}</div>
                                <div class="text-right font-semibold text-red-600">{{ $transaction->type === 'debit' ? number_format((float) $transaction->amount, 2, ',', '.').' TL' : '—' }}</div>
                                <div class="text-right font-semibold text-emerald-600">{{ $transaction->type === 'credit' ? number_format((float) $transaction->amount, 2, ',', '.').' TL' : '—' }}</div>
                                <div class="col-span-2 text-right font-semibold text-slate-900 md:col-span-1">{{ number_format((float) $transaction->running_balance, 2, ',', '.') }} TL</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
