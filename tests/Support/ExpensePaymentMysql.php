<?php

namespace Tests\Support;

/** Explicit opt-in only. Never derives credentials from the application's DB_* settings. */
class ExpensePaymentMysql
{
    public static function configuration(): ?array
    {
        $database = getenv('AIDATCEP_PAYMENT_MYSQL_DATABASE') ?: '';
        if (getenv('AIDATCEP_PAYMENT_MYSQL_ISOLATED') !== '1'
            || ! preg_match('/^aidatcep_payment_test_[a-z0-9_]+$/D', $database)) {
            return null;
        }

        return [
            'driver' => 'mysql', 'host' => '127.0.0.1',
            'port' => getenv('AIDATCEP_PAYMENT_MYSQL_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('AIDATCEP_PAYMENT_MYSQL_USERNAME') ?: '',
            'password' => getenv('AIDATCEP_PAYMENT_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true, 'engine' => 'InnoDB',
        ];
    }
}
