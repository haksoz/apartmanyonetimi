@extends('layouts.app')

@section('content')
    {{-- Header --}}
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-950">Tüm Hareketler</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $account->name }}
                @if($account->unit) &mdash; {{ $account->unit->unit_no }} no.lu daire @endif
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('accounts.statement.export', ['id' => $account->id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
               class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Excel'e Aktar
            </a>
            <a href="{{ route('accounts.statement.import-sample') }}"
               class="rounded-xl bg-slate-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                Şablon İndir
            </a>
            <button type="button" onclick="document.getElementById('import-modal').classList.remove('hidden')"
                    class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                Excel'den İçe Aktar
            </button>
            @if($importedCount > 0)
                <button type="button" onclick="document.getElementById('delete-import-modal').classList.remove('hidden')"
                        class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                    İçe Aktarılmışları Sil
                </button>
            @endif
            <a href="{{ route('accounts.show', $account) }}"
               class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Hesaba Dön
            </a>
        </div>
    </div>

    {{-- Tarih Filtresi --}}
    <form method="GET" action="{{ route('accounts.statement', $account) }}"
          class="rounded-2xl bg-white p-5 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Başlangıç Tarihi</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Bitiş Tarihi</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-950 focus:outline-none">
            </div>
            <button type="submit"
                    class="rounded-xl bg-slate-950 px-6 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 whitespace-nowrap">
                Filtrele
            </button>
            @if($dateFrom || $dateTo)
                <a href="{{ route('accounts.statement', $account) }}"
                   class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 whitespace-nowrap">
                    Temizle
                </a>
            @endif
        </div>
    </form>


    {{-- Hareketler Tablosu --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-950">
                Hareketler
                @if($dateFrom || $dateTo)
                    <span class="ml-2 text-sm font-normal text-slate-500">
                        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') : '—' }}
                        &mdash;
                        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d.m.Y') : 'Bugün' }}
                    </span>
                @endif
            </h2>
            <span class="text-sm text-slate-500">{{ $transactions->count() }} kayıt</span>
        </div>

        @if($transactions->isEmpty())
            <div class="px-6 py-12 text-center text-sm text-slate-500">
                Bu tarih aralığında hareket bulunamadı.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Tarih</th>
                            <th class="px-5 py-3 font-medium">Referans</th>
                            <th class="px-5 py-3 font-medium">Açıklama</th>
                            <th class="px-5 py-3 text-right font-medium">Borç</th>
                            <th class="px-5 py-3 text-right font-medium">Alacak</th>
                            <th class="px-5 py-3 text-right font-medium">Bakiye</th>
                            <th class="px-5 py-3 text-right font-medium">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if($dateFrom)
                            <tr class="bg-slate-50 font-medium">
                                <td class="px-5 py-3.5 text-slate-500">{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</td>
                                <td class="px-5 py-3.5 text-slate-400">—</td>
                                <td class="px-5 py-3.5 text-slate-600">Dönem Açılış Bakiyesi</td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                    {{ $openingBalance > 0 ? number_format($openingBalance, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance < 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $openingBalance < 0 ? number_format(abs($openingBalance), 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right {{ $openingBalance > 0 ? 'text-red-600' : ($openingBalance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                                    {{ number_format(abs($openingBalance), 2, ',', '.') }} TL
                                    @if($openingBalance != 0)
                                        <span class="text-xs font-normal">{{ $openingBalance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right text-slate-400">—</td>
                            </tr>
                        @endif
                        @foreach($transactions as $t)
                            @php
                                $debit  = $t->type === 'debit'  ? $t->amount : 0;
                                $credit = $t->type === 'credit' ? $t->amount : 0;
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-3.5 text-slate-600 whitespace-nowrap">
                                    {{ $t->transaction_date->format('d.m.Y') }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-600 whitespace-nowrap font-mono text-xs">
                                    {{ $t->transactionable?->reference_number ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    {{ $t->description ?: ucfirst($t->type) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-red-600 tabular-nums whitespace-nowrap">
                                    {{ $debit  ? number_format($debit,  2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-emerald-600 tabular-nums whitespace-nowrap">
                                    {{ $credit ? number_format($credit, 2, ',', '.') . ' TL' : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-bold tabular-nums whitespace-nowrap {{ $t->running_balance > 0 ? 'text-red-600' : ($t->running_balance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                                    {{ number_format(abs($t->running_balance), 2, ',', '.') }} TL
                                    @if($t->running_balance != 0)
                                        <span class="text-xs font-normal">{{ $t->running_balance > 0 ? 'B' : 'A' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1">
                                    @if($t->is_imported)
                                        <span class="inline-block rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">İçe Aktarıldı</span>
                                        <form method="POST" action="{{ route('accounts.transactions.destroy', [$account->id, $t->id]) }}" class="inline" onsubmit="return confirm('Bu kayıt silinsin mi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Sil</button>
                                        </form>
                                    @else
                                        @if(($t->transactionable_type ?? '') === \App\Models\Payment::class && $t->transactionable_id)
                                            @if($t->allocations->isNotEmpty())
                                                <button type="button" data-toggle-alloc="alloc-{{ $t->id }}"
                                                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                    Tahsisler
                                                </button>
                                            @endif
                                            <a href="{{ route('payments.show', $t->transactionable_id) }}"
                                               class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                Detay
                                            </a>
                                        @elseif(($t->transactionable_type ?? '') === \App\Models\Due::class && $t->transactionable_id)
                                            <a href="{{ route('dues.show', $t->transactionable_id) }}"
                                               class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                Detay
                                            </a>
                                        @elseif(($t->transactionable_type ?? '') === \App\Models\Expense::class && $t->transactionable_id)
                                            <a href="{{ route('expenses.show', $t->transactionable_id) }}"
                                               class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                                Detay
                                            </a>
                                        @endif
                                    @endif
                                </td>
                            </tr>

                            @if($t->allocations->isNotEmpty())
                                @foreach($t->allocations as $a)
                                    <tr class="bg-slate-50 text-xs alloc-{{ $t->id }} hidden" data-parent="alloc-{{ $t->id }}">
                                        <td class="px-5 py-2"></td>
                                        <td class="px-5 py-2 text-slate-500">
                                            Tahsis &rarr; Aidat
                                            <a href="{{ route('dues.show', $a->due) }}" class="font-medium text-slate-700 hover:text-emerald-600">{{ $a->due->description }}</a>
                                            &mdash; {{ $a->due->due_date->format('d.m.Y') }}
                                        </td>
                                        <td class="px-5 py-2 text-right">—</td>
                                        <td class="px-5 py-2 text-right">—</td>
                                        <td class="px-5 py-2 text-right text-emerald-600 font-medium tabular-nums">{{ number_format($a->amount, 2, ',', '.') }} TL</td>
                                        <td class="px-5 py-2 text-right">—</td>
                                        <td class="px-5 py-2 text-right">
                                            <a href="{{ route('dues.show', $a->due) }}" class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Aidat Detay</a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Kapanış bakiyesi özeti --}}
            <div class="mt-4 p-4 rounded-2xl {{ $closingBalance > 0 ? 'bg-red-50 border border-red-200' : ($closingBalance < 0 ? 'bg-emerald-50 border border-emerald-200' : 'bg-slate-50 border border-slate-200') }}">
                <p class="text-center text-base font-medium {{ $closingBalance > 0 ? 'text-red-700' : ($closingBalance < 0 ? 'text-emerald-700' : 'text-slate-700') }}">
                    Hesabın Toplam
                    <span class="font-bold text-lg mx-1">{{ number_format(abs($closingBalance), 2, ',', '.') }} TL</span>
                    {{ $closingBalance > 0 ? 'borcu vardır' : ($closingBalance < 0 ? 'alacağı vardır' : 'bakiyesi sıfırdır') }}
                </p>
            </div>
        @endif
    </div>

    {{-- Import Modal --}}
    <div id="import-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900 mb-1">Excel'den İçe Aktar</h3>
            <p class="text-sm text-slate-500 mb-3">Hesap hareketlerini Excel dosyasından içeri aktarın.</p>

            <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 space-y-1">
                <p class="font-semibold">İçe aktarmadan önce dikkat edin:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Borç satırları <strong>Devir Öncesi Aidat</strong>, alacak satırları <strong>Devir Öncesi Ödeme</strong> olarak kaydedilir.</li>
                    <li>Alacaklar otomatik olarak <strong>Devir Öncesi Kasası</strong>'na işlenir.</li>
                    <li>İçe aktarılan kayıtları hatalı bulursanız <strong>tahsis yapmadan önce</strong> toplu silme butonu ile tümünü kaldırabilirsiniz. Tahsis yapıldıktan sonra o kayıtlar silinemez.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('accounts.statement.import', $account->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-medium text-slate-600">Excel Dosyası (.xlsx)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" required
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>
                    <p class="text-xs text-slate-400">
                        Henüz şablonunuz yok mu? <a href="{{ route('accounts.statement.import-sample') }}" class="text-blue-600 hover:underline">Şablon indir</a>
                    </p>
                </div>

                <div class="flex gap-3 mt-5">
                    <button type="submit" class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        İçe Aktar
                    </button>
                    <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Devir Öncesi Sil Onay Modal --}}
    @if($importedCount > 0)
    <div id="delete-import-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900 mb-1">İçe Aktarılmışları Sil</h3>

            <div class="my-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-800 space-y-1">
                <p class="font-semibold">Silmeden önce dikkat edin:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Bu hesaba ait <strong>{{ $importedCount }} adet</strong> Devir Öncesi kayıt silinecek.</li>
                    <li>Her kayıtla ilişkili <strong>AccountTransaction</strong> ve kasa hareketi de kaldırılır.</li>
                    <li><strong>Tahsis yapılmış</strong> aidat veya ödemeler korunur, silinemez.</li>
                    <li>Bu işlem geri alınamaz.</li>
                </ul>
            </div>

            <div class="flex gap-3">
                <form method="POST" action="{{ route('accounts.statement.delete-last-import', $account->id) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        Evet, Sil
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('delete-import-modal').classList.add('hidden')"
                    class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">
                    Vazgeç
                </button>
            </div>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-toggle-alloc]');
            if (!btn) return;
            const key = btn.getAttribute('data-toggle-alloc');
            document.querySelectorAll('[data-parent="' + key + '"]').forEach(r => r.classList.toggle('hidden'));
            const open = Array.from(document.querySelectorAll('[data-parent="' + key + '"]')).some(r => !r.classList.contains('hidden'));
            btn.textContent = open ? 'Gizle' : 'Tahsisler';
        });

        // Modal dışına tıklayınca kapat
        document.getElementById('import-modal').addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });

        document.getElementById('delete-import-modal')?.addEventListener('click', function(e) {
            if (e.target === this) this.classList.add('hidden');
        });
    </script>
@endsection
