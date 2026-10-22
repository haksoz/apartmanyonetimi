@if ($row['missing_accounts'] > 0)
    <p class="mb-2 text-xs text-amber-700">{{ $row['missing_accounts'] }} daire için uygun borçlanacak hesap yok. Bu dairelere şu anda borç oluşturulamaz.</p>
@endif
<details>
    <summary class="cursor-pointer text-xs font-semibold text-slate-600">Daireleri ve tutarları göster · {{ number_format($row['total'], 2, ',', '.') }} ₺ taksit toplamı</summary>
    <ul class="mt-2 space-y-2 break-words text-xs text-slate-600">
        @foreach ($row['accounts'] as $account)
            <li>Daire {{ $account['unit'] }} · {{ $account['account'] ?? 'Uygun hesap yok' }} · {{ number_format($account['amount'], 2, ',', '.') }} ₺{{ $account['existing'] ? ' · Mevcut borç korunur' : '' }}</li>
        @endforeach
    </ul>
</details>
