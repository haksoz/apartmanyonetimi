<?php

return [
    'merchant_id' => env('PAYTR_MERCHANT_ID'),
    'merchant_key' => env('PAYTR_MERCHANT_KEY'),
    'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
    'test_mode' => env('PAYTR_TEST_MODE', true),
    // Production additionally requires a closed signup and an explicit test-account allowlist.
    'allow_test_settlement' => env('PAYTR_ALLOW_TEST_SETTLEMENT', false),
    'test_accounts_only' => env('PAYTR_TEST_ACCOUNTS_ONLY', false),
    'test_account_emails' => array_values(array_filter(array_map(
        static fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('PAYTR_TEST_ACCOUNT_EMAILS', '')),
    ))),
    'public_url' => env('PAYTR_PUBLIC_URL', env('APP_URL')),
    'payment_timeout_minutes' => 30,
    // Ignored outside APP_ENV=local and for non-test attempts.
    'local_test_ip' => env('PAYTR_LOCAL_TEST_IP'),
];
