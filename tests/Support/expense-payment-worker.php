<?php

// Child process for the opt-in MySQL concurrency tests; never uses application DB_* credentials.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ExpenseController;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Tests\Support\ExpensePaymentMysql;

$configuration = ExpensePaymentMysql::configuration();
if (! $configuration) exit(2);
[$script, $mode, $idsJson, $apartmentId, $accountId, $cashBoxId, $barrier, $index] = $argv;
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($configuration) {
    $app['config']->set('database.default', 'mysql');
    $app['config']->set('database.connections', ['mysql' => $configuration]);
    $app['config']->set('cache.default', 'array');
    $app['config']->set('session.driver', 'array');
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$apartment = Apartment::findOrFail($apartmentId);
$current = Mockery::mock(CurrentApartment::class);
$current->shouldReceive('getFor')->andReturn($apartment);
$app->instance(CurrentApartment::class, $current);
$user = new User(['name' => 'Concurrency test']);
$user->id = 1;
auth()->setUser($user);
$ids = json_decode($idsJson, true, flags: JSON_THROW_ON_ERROR);
if ($index === '0') {
    $paused = false;
    DB::listen(function ($query) use ($barrier, &$paused) {
        if (! $paused && str_contains($query->sql, '`expenses`') && str_contains($query->sql, 'for update')) {
            $paused = true;
            file_put_contents($barrier.'.locked', 'locked');
            $deadline = microtime(true) + 12;
            while (! is_file($barrier.'.release')) {
                if (microtime(true) > $deadline) throw new RuntimeException('Concurrency barrier timed out.');
                usleep(10000);
            }
        }
    });
} else {
    DB::connection()->beforeExecuting(function ($sql) use ($barrier) {
        if (str_contains($sql, '`accounts`') && str_contains($sql, 'for update')) {
            file_put_contents($barrier.'.started', 'waiting for supplier lock');
        }
    });
}
$request = Illuminate\Http\Request::create('/', 'POST', [
    'amount' => 1000, 'expense_ids' => implode(',', $ids),
    'cash_box_id' => $cashBoxId, 'payment_date' => '2026-10-27',
]);
$app->instance('request', $request);
try {
    if ($mode === 'single') {
        $app->make(ExpenseController::class)->storePayment($request, (string) $ids[0]);
    } else {
        $app->make(AccountController::class)->storeMultiPayExpenses($request, Account::findOrFail($accountId));
    }
    echo 'paid';
} catch (Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
