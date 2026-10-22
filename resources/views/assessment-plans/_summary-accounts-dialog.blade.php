@php
    $accountPeriods = [];
    foreach ($summary['rows'] as $row) {
        $accountPeriods['installment-'.$row['sequence']] = [
            'title' => $row['sequence'].'. Taksit · '.$row['creation_date'].' · Daire Ayrıntıları',
            'description' => count($row['accounts']).' daire · Taksit toplamı '.number_format($row['total'], 2, ',', '.').' ₺ · '.$row['count'].' yeni borç',
            'accounts' => array_map(fn ($account) => [
                'label' => 'Daire '.$account['unit'].' · '.($account['account'] ?? 'Uygun hesap yok'),
                'amount' => $account['amount'],
                'account_type_label' => $account['account_type_label'],
                'note' => match (true) {
                    $account['existing'] => 'Mevcut borç korunur',
                    $row['status'] === 'İptal' => 'Taksit iptal edilmiş',
                    $account['linked'] => 'Önceki borç kaydı artık aktif değil',
                    $account['account_id'] === null => 'Bu daireye şu anda borç oluşturulamaz',
                    default => $row['status'] === 'Oluşturulacak' ? null : $row['status'],
                },
            ], $row['accounts']),
        ];
    }
    $accountPeriods['shares'] = [
        'title' => 'Daire Payları',
        'description' => count($summary['shares']).' daire · Toplam '.number_format(array_sum(array_column($summary['shares'], 'amount')), 2, ',', '.').' ₺',
        'accounts' => array_map(fn ($share) => [
            'label' => 'Daire '.$share['unit'], 'amount' => $share['amount'],
        ], $summary['shares']),
    ];
@endphp
@include('financial-plans._summary-accounts-dialog', [
    'amountLabel' => 'Tutar',
    'decisionSelector' => 'input[name="current_month_action"], input[type="checkbox"][name="include_past_installments"]',
])
