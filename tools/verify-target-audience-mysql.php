<?php

// Source connection is READ ONLY. All DDL/DML is restricted to a fresh clone.
require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
if (($env['APP_ENV'] ?? '') !== 'local' || ($env['DB_CONNECTION'] ?? '') !== 'mysql'
    || ! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
    || ($env['DB_DATABASE'] ?? '') !== 'apartman_db') {
    throw new RuntimeException('Expected the explicitly local apartman_db source.');
}
if (file_exists($root.'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Cached Laravel configuration prevents safe isolated bootstrap.');
}
$dsn = 'mysql:host='.$env['DB_HOST'].';port='.($env['DB_PORT'] ?? '3306').';charset=utf8mb4';
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
$source = new PDO($dsn.';dbname='.$env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', $options);
$source->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$source->exec('SET SESSION TRANSACTION READ ONLY');
$source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
$metadata = $source->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
$triggers = (int) $source->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
$events = (int) $source->query('SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()')->fetchColumn();
$routines = (int) $source->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')->fetchColumn();
$unsafeTables = array_values(array_filter($metadata, fn ($t) => $t['TABLE_TYPE'] !== 'BASE TABLE' || $t['ENGINE'] !== 'InnoDB'));
$grants = $source->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
$report = ['checked_at_utc' => gmdate('c'), 'source_database' => $env['DB_DATABASE'],
    'server_version' => $source->query('SELECT VERSION()')->fetchColumn(), 'source_connection_read_only' => true,
    'tables' => count($metadata), 'unsafe_tables' => $unsafeTables, 'triggers' => $triggers, 'events' => $events,
    'routines' => $routines, 'can_grant' => (bool) array_filter($grants, fn ($grant) => str_contains($grant, 'WITH GRANT OPTION')),
    'grant_scopes' => array_map(fn ($grant) => explode(' TO ', $grant, 2)[0], $grants),
    'global_all_privileges' => (bool) array_filter($grants, fn ($grant) => str_contains($grant, 'GRANT ALL PRIVILEGES ON *.*'))];
if ($unsafeTables || $triggers || $events || $routines) {
    $source->exec('ROLLBACK');
    throw new RuntimeException('Unsafe schema objects prevent unattended cloning: '.json_encode($report));
}
if (($argv[1] ?? '') === '--preflight') {
    $source->exec('ROLLBACK');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    exit;
}
if (! in_array($argv[1] ?? '', ['--run', '--resume'], true) || ! $report['can_grant']) {
    throw new RuntimeException('Use --run only with permission to create a restricted clone account.');
}

function identifier(string $name): string
{
    if (! preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new RuntimeException('Unexpected SQL identifier.');
    return '`'.$name.'`';
}

function ensure(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

function digestRows(array $rows, bool $excludeTarget = false): array
{
    $encoded = [];
    foreach ($rows as $row) {
        if ($excludeTarget) unset($row['target_audience']);
        ksort($row);
        $encoded[] = json_encode($row, JSON_THROW_ON_ERROR);
    }
    sort($encoded, SORT_STRING);
    return ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $encoded))];
}

function snapshot(PDO $pdo, array $tables, bool $excludeTarget = false): array
{
    $result = [];
    foreach ($tables as $table) {
        $result[$table] = digestRows($pdo->query('SELECT * FROM '.identifier($table))->fetchAll(PDO::FETCH_ASSOC), $excludeTarget);
    }
    return $result;
}

function akbeySnapshot(PDO $pdo): array
{
    $result = [];
    foreach (['dues', 'payments', 'account_transactions', 'cash_transactions', 'cash_boxes', 'accounts'] as $table) {
        $rows = $pdo->query('SELECT * FROM '.identifier($table).' WHERE apartment_id=8 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $result[$table] = digestRows($rows, true);
        $columns = $pdo->query('SHOW COLUMNS FROM '.identifier($table))->fetchAll(PDO::FETCH_COLUMN);
        foreach (['amount', 'remaining_amount', 'unallocated_amount'] as $column) {
            if (in_array($column, $columns)) {
                $result[$table]['sum_'.$column] = $pdo->query('SELECT SUM('.identifier($column).') FROM '.identifier($table).' WHERE apartment_id=8')->fetchColumn();
            }
        }
    }
    $result['payment_allocations'] = digestRows($pdo->query('SELECT x.* FROM payment_allocations x JOIN payments p ON p.id=x.payment_id WHERE p.apartment_id=8 ORDER BY x.id')->fetchAll(PDO::FETCH_ASSOC));
    $result['active_dues'] = $pdo->query('SELECT COUNT(*) AS records,SUM(amount) AS amount,SUM(remaining_amount) AS remaining_amount FROM dues WHERE apartment_id=8 AND deleted_at IS NULL')->fetch(PDO::FETCH_ASSOC);
    return $result;
}

$tables = array_column($metadata, 'TABLE_NAME');
$audienceTables = ['dues', 'due_batches', 'due_plans', 'assessment_plans'];
$migrationName = '2026_10_29_000001_drop_target_audience_from_due_tables';
$sourceBefore = snapshot($source, $tables);
$financialBefore = snapshot($source, array_values(array_diff($tables, ['migrations'])), true);
$akbeyBefore = akbeySnapshot($source);
ensure($akbeyBefore['dues']['count'] === 1131, 'AKBEY source count differs from the approved 1131 records.');
$backup = [];
foreach ($audienceTables as $table) {
    $backup[$table] = $source->query('SELECT id,target_audience FROM '.identifier($table).' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}
ensure((int) $source->query("SELECT COUNT(*) FROM migrations WHERE migration='$migrationName'")->fetchColumn() === 0, 'Source already records the removal migration.');

$suffix = bin2hex(random_bytes(6));
$clone = 'aidatcepstage2test'.gmdate('Ymd').$suffix;
$resuming = ($argv[1] ?? '') === '--resume';
if ($resuming) {
    $clone = $argv[2] ?? '';
    ensure(preg_match('/^aidatcepstage2test[0-9]{8}[a-f0-9]{12}$/D', $clone) === 1, 'Invalid resume clone name.');
    $previous = null;
    foreach (glob($root.'/docs/analysis/target-audience-mysql-verification-*.json') as $path) {
        $candidate = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (($candidate['clone_database'] ?? '') === $clone && isset($candidate['source_before_fingerprints'])) {
            $previous = $candidate;
            $report['resumed_from_report'] = basename($path);
        }
    }
    ensure($previous !== null && $previous['source_before_fingerprints'] === $sourceBefore, 'Resume requires unchanged source and a recorded owned clone.');
    $suffix = substr($clone, -12);
}
$cloneUser = 'aidatst2_'.$suffix;
$clonePassword = bin2hex(random_bytes(24));
$principal = "'".$cloneUser."'@'localhost'";
ensure(preg_match('/^aidatcepstage2test[0-9]{8}[a-f0-9]{12}$/D', $clone) === 1 && $clone !== $env['DB_DATABASE'], 'Invalid clone name.');
$report['clone_database'] = $clone;
$report['clone_user'] = $cloneUser.'@localhost';
$report['retained_for_review'] = true;
$report['source_before_fingerprints'] = $sourceBefore;
$report['stage'] = 'create_isolation';
$reportPath = $root.'/docs/analysis/target-audience-mysql-verification-'.gmdate('Y-m-d-His').'.json';
$saveReport = function () use (&$report, $reportPath): void {
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
};
$admin = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', $options);
$userCreated = false;
$app = null;
try {
    if ($resuming) {
        $inspection = new PDO($dsn.';dbname='.$clone, $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', $options);
        ensure(snapshot($inspection, array_values(array_diff($tables, ['migrations'])), true) === $financialBefore, 'Owned clone financial rows differ; refuse resume.');
        ensure((int) $inspection->query("SELECT COUNT(*) FROM migrations WHERE migration='$migrationName'")->fetchColumn() === 1, 'Resume expects the migration to be applied.');
        $admin->exec('ALTER USER '.$principal.' IDENTIFIED BY '.$admin->quote($clonePassword).' ACCOUNT UNLOCK');
        $userCreated = true;
        $report['reused_owned_clone'] = true;
    } else {
        // No IF NOT EXISTS: never reuse or overwrite another database/account.
        $admin->exec('CREATE DATABASE '.identifier($clone).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $report['database_created'] = true;
        $saveReport();
        $admin->exec('CREATE USER '.$principal.' IDENTIFIED BY '.$admin->quote($clonePassword));
        $userCreated = true;
        $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES ON '.identifier($clone).'.* TO '.$principal);
    }
    $target = new PDO($dsn.';dbname='.$clone, $cloneUser, $clonePassword, $options);
    ensure($target->query('SELECT DATABASE()')->fetchColumn() === $clone, 'Wrong clone connection.');
    try {
        $target->query('SELECT id FROM apartman_db.dues LIMIT 1');
        throw new RuntimeException('Restricted user can access the source; isolation is unsafe.');
    } catch (PDOException $exception) {
        ensure(in_array($exception->errorInfo[1] ?? null, [1044, 1142], true), 'Unexpected isolation probe failure.');
        $report['source_access_denied_to_clone_user'] = true;
    }
    $report['stage'] = 'copy_read_only_snapshot';
    $saveReport();
    if (! $resuming) {
    $target->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $definition = $source->query('SHOW CREATE TABLE '.identifier($table))->fetch(PDO::FETCH_NUM)[1];
        ensure(! str_contains($definition, '`'.$env['DB_DATABASE'].'`.'), 'Cross-schema reference in source DDL.');
        $target->exec($definition);
    }
    $target->beginTransaction();
    foreach ($tables as $table) {
        $columns = $source->query('SHOW COLUMNS FROM '.identifier($table))->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column(array_filter($columns, fn ($c) => ! preg_match('/(?:VIRTUAL|STORED) GENERATED/', $c['Extra'])), 'Field');
        $columnSql = implode(',', array_map('identifier', $names));
        $insert = $target->prepare('INSERT INTO '.identifier($table).' ('.$columnSql.') VALUES ('.implode(',', array_fill(0, count($names), '?')).')');
        $rows = $source->query('SELECT '.$columnSql.' FROM '.identifier($table));
        while ($row = $rows->fetch(PDO::FETCH_NUM)) $insert->execute($row);
    }
    $target->commit();
    $target->exec('SET FOREIGN_KEY_CHECKS=1');
    ensure(snapshot($target, $tables) === $sourceBefore, 'Clone row fingerprints do not match the source snapshot.');
    $report['all_52_tables_copied_exactly'] = true;
    $report['copied_row_counts'] = array_map(fn ($s) => $s['count'], $sourceBefore);
    }
    $source->exec('ROLLBACK');
    $report['akbey_before'] = $akbeyBefore;
    $saveReport();

    // Environment is changed before bootstrapping. Laravel never sees admin credentials.
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $env['DB_HOST'],
        'DB_PORT' => $env['DB_PORT'] ?? '3306', 'DB_DATABASE' => $clone, 'DB_USERNAME' => $cloneUser,
        'DB_PASSWORD' => $clonePassword, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    config(['database.connections' => ['mysql' => config('database.connections.mysql')], 'database.default' => 'mysql']);
    ensure(Illuminate\Support\Facades\DB::connection()->getDatabaseName() === $clone, 'Laravel is not using the clone.');
    ensure(Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name === $clone, 'Laravel server connection mismatch.');
    $app['session']->driver()->start();
    if ($resuming) {
        $resetArgs = ['--database' => 'mysql', '--path' => 'database/migrations/'.$migrationName.'.php', '--force' => true, '--step' => 1];
        ensure($kernel->call('migrate:rollback', $resetArgs) === 0, 'Owned clone reset rollback failed.');
        $target->beginTransaction();
        foreach ($backup as $table => $rows) {
            $update = $target->prepare('UPDATE '.identifier($table).' SET target_audience=? WHERE id=?');
            foreach ($rows as $row) $update->execute([$row['target_audience'], $row['id']]);
        }
        $target->commit();
        ensure(snapshot($target, $tables) === $sourceBefore, 'Owned clone reset does not match source snapshot.');
        $report['owned_clone_reset_matches_all_source_rows'] = true;
        $saveReport();
    }

    $ledgerBalances = fn () => App\Models\Account::withTrashed()->where('apartment_id', 8)->orderBy('id')->get()
        ->mapWithKeys(fn ($account) => [$account->id => $account->ledger_balance])->all();
    $ledgerBefore = $ledgerBalances();
    $reportData = function (int $apartmentId, string $month, string $type, string $mode): array {
        $apartment = App\Models\Apartment::findOrFail($apartmentId);
        Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail($apartment->user_id));
        session([App\Support\CurrentApartment::SESSION_KEY => $apartmentId]);
        $request = Illuminate\Http\Request::create('/reports/monthly-board', 'GET', ['month' => $month, 'due_type' => $type, 'view_mode' => $mode]);
        app()->instance('request', $request);
        $view = app(App\Http\Controllers\ReportController::class)->monthlyBoard(app(App\Support\CurrentApartment::class), $request);
        ensure($view instanceof Illuminate\View\View, 'Monthly report redirected instead of producing data.');
        return $view->getData();
    };
    $months = $target->query("SELECT DISTINCT COALESCE(LEFT(period,7),DATE_FORMAT(due_date,'%Y-%m')) AS month FROM dues WHERE apartment_id=8 ORDER BY month")->fetchAll(PDO::FETCH_COLUMN);
    $reportSnapshot = function () use ($months, $reportData): array {
        $result = [];
        foreach ($months as $month) foreach (['aidat', 'malik_payi'] as $type) foreach (['current', 'movements'] as $mode) {
            $data = $reportData(8, $month, $type, $mode);
            $result[$month.'/'.$type.'/'.$mode] = hash('sha256', json_encode($data['accountData'], JSON_THROW_ON_ERROR));
        }
        return $result;
    };
    $reportsBefore = $reportSnapshot();
    $report['stage'] = 'migration_up';
    $saveReport();
    $migrationArgs = ['--database' => 'mysql', '--path' => 'database/migrations/'.$migrationName.'.php', '--force' => true];
    $started = microtime(true);
    ensure($kernel->call('migrate', $migrationArgs) === 0, 'Clone migration failed.');
    $report['migration_up_seconds'] = round(microtime(true) - $started, 4);
    $columnsAbsent = function () use ($target, $audienceTables): array {
        $result = [];
        foreach ($audienceTables as $table) {
            $result[$table] = ! in_array('target_audience', $target->query('SHOW COLUMNS FROM '.identifier($table))->fetchAll(PDO::FETCH_COLUMN));
        }
        return $result;
    };
    $report['columns_removed'] = $columnsAbsent();
    ensure(! in_array(false, $report['columns_removed'], true), 'A target column remains.');
    ensure(snapshot($target, array_values(array_diff($tables, ['migrations'])), true) === $financialBefore, 'A non-target row changed during migration.');
    ensure(akbeySnapshot($target) === $akbeyBefore, 'AKBEY financial snapshot changed during migration.');
    ensure($ledgerBalances() === $ledgerBefore, 'AKBEY account ledger balances changed.');
    ensure($reportSnapshot() === $reportsBefore, 'AKBEY monthly report calculations changed.');
    $report['akbey_after_up'] = akbeySnapshot($target);
    $report['akbey_ledger_balances_unchanged'] = true;
    $report['akbey_report_scenarios_unchanged'] = count($reportsBefore);
    $report['all_non_target_rows_unchanged_after_up'] = true;
    $report['stage'] = 'application_flows';
    $saveReport();

    // All generated fixtures live in a new apartment and an explicitly rolled-back transaction.
    Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $report['application_flows'] = require $root.'/tools/verify-target-audience-mysql-flows.php';
    } finally {
        while (Illuminate\Support\Facades\DB::transactionLevel() > 0) Illuminate\Support\Facades\DB::rollBack();
    }
    ensure(snapshot($target, array_values(array_diff($tables, ['migrations'])), true) === $financialBefore, 'Application fixtures were not fully rolled back.');

    $report['stage'] = 'rollback_and_backup_recovery';
    $saveReport();
    ensure($kernel->call('migrate:rollback', [...$migrationArgs, '--step' => 1]) === 0, 'Clone migration rollback failed.');
    ensure(! in_array(true, $columnsAbsent(), true), 'Rollback did not recreate all columns.');
    ensure(snapshot($target, array_values(array_diff($tables, ['migrations'])), true) === $financialBefore, 'Rollback changed financial data.');
    $differences = [];
    foreach ($backup as $table => $rows) {
        $current = $target->query('SELECT id,target_audience FROM '.identifier($table).' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $byId = array_column($current, 'target_audience', 'id');
        $differences[$table] = count(array_filter($rows, fn ($row) => $byId[$row['id']] !== $row['target_audience']));
    }
    ensure(array_sum($differences) > 0, 'Unexpectedly no lost historical target values after rollback.');
    $report['rollback_does_not_restore_original_values'] = $differences;
    // Restore only the clone's targeting columns from the snapshot kept in memory.
    $target->beginTransaction();
    foreach ($backup as $table => $rows) {
        $update = $target->prepare('UPDATE '.identifier($table).' SET target_audience=? WHERE id=?');
        foreach ($rows as $row) $update->execute([$row['target_audience'], $row['id']]);
    }
    $target->commit();
    ensure(snapshot($target, $tables) === $sourceBefore, 'In-memory backup recovery did not restore all original rows.');
    $report['backup_recovery_restores_original_rows_exactly'] = true;
    ensure($kernel->call('migrate', $migrationArgs) === 0, 'Clone re-application failed.');
    ensure(! in_array(false, $columnsAbsent(), true), 'Re-applied migration left a target column.');
    ensure(akbeySnapshot($target) === $akbeyBefore, 'Final clone AKBEY data differs.');
    $report['clone_final_state'] = 'migration applied; all four columns absent; generated fixtures rolled back';
    $report['stage'] = 'final_source_read_only_comparison';
    $saveReport();
    $source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    ensure(snapshot($source, $tables) === $sourceBefore, 'Source changed since the snapshot. Stop and investigate concurrent activity.');
    $source->exec('ROLLBACK');
    $report['source_all_52_table_rows_unchanged'] = true;
    $report['source_columns_still_present'] = [];
    foreach ($audienceTables as $table) {
        $report['source_columns_still_present'][$table] = in_array('target_audience', $source->query('SHOW COLUMNS FROM '.identifier($table))->fetchAll(PDO::FETCH_COLUMN));
    }
    $report['stage'] = 'complete';
    $report['passed'] = true;
} catch (Throwable $exception) {
    $report['passed'] = false;
    $report['error'] = ['type' => get_class($exception), 'code' => $exception->getCode(),
        'message' => $exception instanceof PDOException ? 'Database operation failed; no automatic repair performed.' : $exception->getMessage(),
        'file' => basename($exception->getFile()), 'line' => $exception->getLine()];
    $report['error']['tool_trace'] = array_values(array_map(fn ($frame) => ['file' => basename($frame['file']), 'line' => $frame['line'] ?? null],
        array_filter($exception->getTrace(), fn ($frame) => isset($frame['file']) && str_contains(str_replace('\\', '/', $frame['file']), '/tools/'))));
} finally {
    if ($source->inTransaction()) $source->exec('ROLLBACK');
    if (isset($target) && $target->inTransaction()) $target->rollBack();
    $source->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $report['source_all_52_table_rows_unchanged'] = snapshot($source, $tables) === $sourceBefore;
    $source->exec('ROLLBACK');
    if (! $report['source_all_52_table_rows_unchanged']) $report['passed'] = false;
    if ($userCreated) {
        $admin->exec('ALTER USER '.$principal.' ACCOUNT LOCK');
        $report['clone_user_locked_after_run'] = true;
    }
    $saveReport();
}
echo json_encode(['report' => $reportPath, 'passed' => $report['passed'] ?? false, 'stage' => $report['stage'],
    'clone_database' => $clone, 'error' => $report['error'] ?? null], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(($report['passed'] ?? false) ? 0 : 1);
