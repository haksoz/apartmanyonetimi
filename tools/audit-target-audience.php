<?php

// Standalone, local-only, read-only preflight. Does not bootstrap Laravel.
require dirname(__DIR__).'/vendor/autoload.php';

$env = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__).'/.env'));
if (($env['DB_CONNECTION'] ?? '') !== 'mysql' || ! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
    || ($env['APP_ENV'] ?? '') !== 'local') {
    throw new RuntimeException('Only an explicitly local MySQL database is allowed.');
}
$pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.($env['DB_PORT'] ?? '3306').';dbname='.$env['DB_DATABASE'].';charset=utf8mb4',
    $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
    $report = ['scope' => 'local MySQL; all apartments; no soft-delete filters; read-only snapshot', 'checked_at_utc' => gmdate('c')];
    $report['apartments'] = $pdo->query('SELECT id FROM apartments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans'] as $table) {
        $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        $report['tables'][$table]['columns_present'] = array_values(array_intersect(['due_type', 'target_audience', 'deleted_at'], $columns));
        if (! in_array('due_type', $columns) || ! in_array('target_audience', $columns)) {
            $report['tables'][$table]['blocked'] = 'Missing required audit columns';
            continue;
        }
        $deleted = in_array('deleted_at', $columns) ? '(deleted_at IS NOT NULL)' : '0';
        $report['tables'][$table]['groups'] = $pdo->query("SELECT apartment_id, $deleted AS soft_deleted, due_type, target_audience, COUNT(*) AS records FROM `$table` GROUP BY apartment_id, soft_deleted, due_type, target_audience ORDER BY apartment_id, soft_deleted, due_type, target_audience")->fetchAll(PDO::FETCH_ASSOC);
        $report['tables'][$table]['issues'] = $pdo->query("SELECT id, apartment_id, $deleted AS soft_deleted, due_type, target_audience, CASE WHEN target_audience IS NULL THEN 'null_target' WHEN TRIM(target_audience) = '' THEN 'empty_target' WHEN target_audience NOT IN ('tenant_priority','owner_only') THEN 'invalid_target' WHEN due_type IS NULL OR due_type NOT IN ('aidat','malik_payi') THEN 'invalid_due_type' ELSE 'mismatch' END AS issue FROM `$table` WHERE target_audience IS NULL OR target_audience NOT IN ('tenant_priority','owner_only') OR due_type IS NULL OR due_type NOT IN ('aidat','malik_payi') OR (due_type = 'aidat' AND target_audience <> 'tenant_priority') OR (due_type = 'malik_payi' AND target_audience <> 'owner_only') ORDER BY apartment_id, id")->fetchAll(PDO::FETCH_ASSOC);
        if (in_array($table, ['due_plans', 'assessment_plans'])) {
            $expected = $table === 'due_plans' ? 'aidat' : 'malik_payi';
            $report['tables'][$table]['unsupported_plan_types'] = $pdo->query("SELECT id, apartment_id, due_type FROM `$table` WHERE due_type IS NULL OR due_type <> '$expected'")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    foreach (['dues', 'payments', 'payment_allocations', 'account_transactions', 'cash_boxes', 'cash_transactions'] as $table) {
        $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        $select = ['COUNT(*) AS records'];
        foreach (['amount', 'remaining_amount', 'unallocated_amount', 'balance'] as $column) {
            if (in_array($column, $columns)) $select[] = "SUM(`$column`) AS `sum_$column`";
        }
        $report['financial_snapshot'][$table] = $pdo->query('SELECT '.implode(', ', $select).' FROM `'.$table.'`')->fetch(PDO::FETCH_ASSOC);
    }
    $checks = [
        'due_account_reference' => 'SELECT d.id, d.apartment_id FROM dues d LEFT JOIN accounts a ON a.id=d.account_id WHERE a.id IS NULL OR a.apartment_id<>d.apartment_id',
        'payment_account_reference' => 'SELECT p.id, p.apartment_id FROM payments p LEFT JOIN accounts a ON a.id=p.account_id WHERE a.id IS NULL OR a.apartment_id<>p.apartment_id',
        'allocation_reference' => 'SELECT x.id FROM payment_allocations x LEFT JOIN payments p ON p.id=x.payment_id LEFT JOIN dues d ON d.id=x.due_id LEFT JOIN expenses e ON e.id=x.expense_id WHERE p.id IS NULL OR (x.due_id IS NOT NULL AND d.id IS NULL) OR (x.expense_id IS NOT NULL AND e.id IS NULL) OR (x.due_id IS NULL AND x.expense_id IS NULL) OR (x.due_id IS NOT NULL AND x.expense_id IS NOT NULL) OR x.amount<=0 OR (d.id IS NOT NULL AND (d.apartment_id<>p.apartment_id OR d.account_id<>p.account_id)) OR (e.id IS NOT NULL AND e.apartment_id<>p.apartment_id)',
        'due_balance' => 'SELECT d.id, d.apartment_id, d.deleted_at, d.amount, d.remaining_amount, COALESCE(x.allocated,0) AS allocated FROM dues d LEFT JOIN (SELECT due_id,SUM(amount) allocated FROM payment_allocations GROUP BY due_id) x ON x.due_id=d.id WHERE d.amount<0 OR d.remaining_amount<0 OR d.remaining_amount>d.amount OR ABS(d.amount-d.remaining_amount-COALESCE(x.allocated,0))>0.01',
        'payment_balance' => 'SELECT p.id, p.apartment_id, p.amount,p.unallocated_amount,COALESCE(x.allocated,0) AS allocated FROM payments p LEFT JOIN (SELECT payment_id,SUM(amount) allocated FROM payment_allocations GROUP BY payment_id) x ON x.payment_id=p.id WHERE p.amount<0 OR p.unallocated_amount<0 OR p.unallocated_amount>p.amount OR ABS(p.amount-p.unallocated_amount-COALESCE(x.allocated,0))>0.01',
        'transaction_account_reference' => 'SELECT t.id,t.apartment_id FROM account_transactions t LEFT JOIN accounts a ON a.id=t.account_id WHERE a.id IS NULL OR a.apartment_id<>t.apartment_id',
        'cash_reference' => 'SELECT c.id,c.apartment_id FROM cash_transactions c LEFT JOIN cash_boxes b ON b.id=c.cash_box_id LEFT JOIN payments p ON p.id=c.payment_id LEFT JOIN accounts a ON a.id=c.account_id WHERE b.id IS NULL OR b.apartment_id<>c.apartment_id OR (c.payment_id IS NOT NULL AND (p.id IS NULL OR p.apartment_id<>c.apartment_id)) OR (c.account_id IS NOT NULL AND (a.id IS NULL OR a.apartment_id<>c.apartment_id))',
    ];
    foreach ($checks as $name => $sql) {
        $report['integrity_checks'][$name] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach (['Due' => 'dues', 'Payment' => 'payments', 'Expense' => 'expenses'] as $model => $table) {
        $statement = $pdo->prepare("SELECT t.id,t.apartment_id,t.transactionable_id FROM account_transactions t LEFT JOIN `$table` r ON r.id=t.transactionable_id WHERE t.transactionable_type=? AND (r.id IS NULL OR r.apartment_id<>t.apartment_id OR r.account_id<>t.account_id)");
        $statement->execute(['App\\Models\\'.$model]);
        $report['integrity_checks']['transaction_'.$table.'_reference'] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    $report['integrity_checks']['due_batch_type'] = $pdo->query('SELECT d.id,d.apartment_id FROM dues d LEFT JOIN due_batches b ON b.id=d.due_batch_id WHERE d.due_batch_id IS NOT NULL AND (b.id IS NULL OR b.apartment_id<>d.apartment_id OR b.due_type<>d.due_type)')->fetchAll(PDO::FETCH_ASSOC);
    $report['integrity_checks']['cash_payment_account'] = $pdo->query('SELECT c.id,c.apartment_id FROM cash_transactions c JOIN payments p ON p.id=c.payment_id WHERE c.account_id IS NOT NULL AND c.account_id<>p.account_id')->fetchAll(PDO::FETCH_ASSOC);
    $report['integrity_checks']['allocations_to_deleted_dues'] = $pdo->query('SELECT x.id,d.id AS due_id,d.apartment_id FROM payment_allocations x JOIN dues d ON d.id=x.due_id WHERE d.deleted_at IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
    foreach (['due_plans' => 'due_plan_id', 'assessment_plans' => 'assessment_plan_id'] as $table => $foreignKey) {
        $report['integrity_checks']['batch_'.$table] = $pdo->query("SELECT b.id,b.apartment_id FROM due_batches b LEFT JOIN `$table` p ON p.id=b.`$foreignKey` WHERE b.`$foreignKey` IS NOT NULL AND (p.id IS NULL OR p.apartment_id<>b.apartment_id OR p.due_type<>b.due_type)")->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans', 'payments', 'payment_allocations', 'account_transactions', 'cash_boxes', 'cash_transactions'] as $table) {
        $hash = hash_init('sha256');
        $rows = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY id');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
        $report['row_fingerprints'][$table] = hash_final($hash);
    }
    $report['null_due_relationships'] = $pdo->query('SELECT d.id,d.apartment_id,d.due_type,d.deleted_at,d.amount,d.remaining_amount,(SELECT COUNT(*) FROM payment_allocations x WHERE x.due_id=d.id) allocation_count,(SELECT COUNT(*) FROM account_transactions t WHERE t.transactionable_type=CONCAT(CHAR(65,112,112,92,77,111,100,101,108,115,92),CHAR(68,117,101)) AND t.transactionable_id=d.id) transaction_count FROM dues d WHERE d.target_audience IS NULL ORDER BY d.id')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $pdo->exec('ROLLBACK');
}
