@php
    $accountPeriods = collect($summary['rows'])->filter(fn ($row) => $row['count'] > 0)
        ->mapWithKeys(fn ($row) => [$row['period'] => [
            'label' => $row['label'], 'amount' => $row['amount'],
            'accounts' => array_map(fn ($account) => ['label' => $account['label'], 'amount' => $account['amount'], 'account_type_label' => $account['account_type_label']], $row['accounts']),
        ]]);
@endphp
@include('financial-plans._summary-accounts-dialog')
