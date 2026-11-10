<div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-700">Geçmiş Dönemler</h2>
        <p class="mt-1 text-xs text-slate-500">Seçili dönemin başlangıcından önceki gelir ve giderler, yıl bazında gösterilir.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Yıl</th>
                    <th class="px-4 py-3 text-right">Gider</th>
                    <th class="px-4 py-3 text-right">Gelir</th>
                    <th class="px-4 py-3 text-right">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($historyRows as $history)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $history['year'] }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $history['period'] }}</span></td>
                        <td class="px-4 py-3 text-right text-red-500">{{ number_format($history['expense'], 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right text-emerald-600">{{ number_format($history['income'], 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $history['net'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">{{ number_format($history['net'], 2, ',', '.') }} ₺</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Seçili dönemden önce kayıt bulunamadı.</td></tr>
                @endforelse
            </tbody>
            @if($historyRows->isNotEmpty())
                <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold">
                    <tr>
                        <td class="px-4 py-3">GEÇMİŞ TOPLAMI</td>
                        <td class="px-4 py-3 text-right">{{ number_format($historyRows->sum('expense'), 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right">{{ number_format($historyRows->sum('income'), 2, ',', '.') }} ₺</td>
                        <td class="px-4 py-3 text-right">{{ number_format($openingBalance, 2, ',', '.') }} ₺</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
