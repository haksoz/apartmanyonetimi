@if ($row['missing_accounts'] > 0)
    <p class="mb-2 text-xs text-amber-700">{{ $row['missing_accounts'] }} daire için uygun borçlanacak hesap yok. Bu dairelere şu anda borç oluşturulamaz.</p>
@endif
@if (count($row['accounts']) > 0)
    <button type="button" data-period-accounts="installment-{{ $row['sequence'] }}" aria-haspopup="dialog" aria-controls="period-accounts-dialog" aria-label="{{ $row['sequence'] }}. taksit dairelerini ve tutarlarını göster" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">Daireleri ve tutarları göster · {{ number_format($row['total'], 2, ',', '.') }} ₺</button>
@endif
