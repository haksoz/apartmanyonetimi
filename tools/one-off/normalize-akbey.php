<?php

// One-time cleanup of the legacy Akbey import. Default mode validates and rolls back.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function normalizedName(string $name): string
{
    $name = strtr(mb_strtolower($name, 'UTF-8'), ['ı' => 'i', 'İ' => 'i']);
    $name = preg_replace('/\s+kat\s+maliki\s*$/u', '', trim($name));

    return preg_replace('/\s+/u', ' ', trim($name));
}

function fingerprint(string $table, array $ignored = []): string
{
    $hash = hash_init('sha256');
    foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
        $data = (array) $row;
        foreach ($ignored as $column) {
            unset($data[$column]);
        }
        hash_update($hash, json_encode($data, JSON_THROW_ON_ERROR));
    }

    return hash_final($hash);
}

function totals(int $apartmentId): array
{
    $result = [];
    foreach (['dues' => ['amount', 'remaining_amount'], 'payments' => ['amount', 'unallocated_amount'],
        'account_transactions' => ['amount'], 'cash_transactions' => ['amount']] as $table => $columns) {
        $query = DB::table($table)->where('apartment_id', $apartmentId);
        $result[$table]['count'] = $query->count();
        foreach ($columns as $column) {
            $result[$table][$column] = (string) $query->sum($column);
        }
    }

    return $result;
}

$apply = in_array('--apply', $argv, true);
$apartmentId = 8;
$reportPath = storage_path('app/data-fixes/akbey-account-merge.json');
ensure(! file_exists($reportPath), 'This cleanup already has a completed report; refusing to repeat it.');
ensure(DB::table('apartments')->where('id', $apartmentId)->value('name') === 'Akbey Apartmanı', 'Unexpected apartment.');
$expected = [136 => 116, 138 => 114, 140 => 117, 143 => 120, 144 => 121, 146 => 123,
    147 => 124, 148 => 125, 158 => 118];
$financialTables = ['dues', 'payments', 'account_transactions', 'cash_transactions', 'expenses'];

DB::beginTransaction();
try {
    $accounts = Account::where('apartment_id', $apartmentId)->lockForUpdate()->get();
    $owners = $accounts->where('type', Account::TYPE_OWNER);
    ensure($owners->count() === 12, 'Expected 12 owner accounts.');
    $pairs = [];
    foreach ($accounts->where('type', Account::TYPE_TENANT) as $source) {
        $matches = $owners->filter(fn ($owner) => $owner->unit_id === $source->unit_id
            && normalizedName($owner->name) === normalizedName($source->name));
        ensure($matches->count() <= 1, 'Ambiguous name match.');
        if ($matches->isEmpty()) {
            continue;
        }
        $target = $matches->first();
        ensure($source->user_id === null, 'Source has a linked portal user.');
        ensure((float) $source->balance === 0.0, 'Source has a stored balance needing review.');
        $pairs[$source->id] = $target->id;
    }
    ksort($pairs);
    ksort($expected);
    ensure($pairs === $expected, 'Account matching differs from reviewed pairs.');
    $sourceIds = array_keys($pairs);

    // Any additional dependency needs explicit handling rather than a partial merge.
    $seen = [];
    foreach (Schema::getTables() as $table) {
        $name = $table['name'];
        if (isset($seen[$name]) || ! Schema::hasColumn($name, 'account_id')) {
            continue;
        }
        $seen[$name] = true;
        if (! in_array($name, [...$financialTables, 'tenant_assignments'], true)) {
            ensure(! DB::table($name)->whereIn('account_id', $sourceIds)->exists(), 'Unhandled dependency: '.$name);
        }
    }

    $before = totals($apartmentId);
    $fingerprints = [];
    foreach ($financialTables as $table) {
        $fingerprints[$table] = fingerprint($table, $table === 'dues' ? ['account_id', 'due_type'] : ['account_id']);
    }
    $allocationsBefore = fingerprint('payment_allocations');
    $assignmentsBefore = fingerprint('tenant_assignments');
    $untouchedAccountIds = DB::table('accounts')->whereNotIn('id', $sourceIds)->pluck('id');
    $untouchedAccountsBefore = DB::table('accounts')->whereIn('id', $untouchedAccountIds)->orderBy('id')->get()->toJson();
    $unrelatedDues = DB::table('dues')->whereNotIn('account_id', [...$sourceIds, ...$owners->pluck('id')->all()])->orderBy('id')->get()->toJson();
    $report = ['apartment_id' => $apartmentId, 'apartment' => 'Akbey Apartmanı', 'before' => $before,
        'pairs' => [], 'completed_at' => null];

    // Only the debts belonging to owners BEFORE the merge change their debt type.
    $ownerDues = DB::table('dues')->where('apartment_id', $apartmentId)->whereIn('account_id', $owners->pluck('id'));
    ensure(! (clone $ownerDues)->where('due_type', '!=', 'aidat')->exists(), 'Owner debt types differ from the reviewed data.');
    $ownerDueIds = (clone $ownerDues)->pluck('id');
    $report['active_owner_debts_retyped'] = (clone $ownerDues)->whereNull('deleted_at')->count();
    $report['owner_debts_retyped'] = (clone $ownerDues)->update(['due_type' => 'malik_payi']);

    foreach ($pairs as $sourceId => $targetId) {
        $source = $accounts->firstWhere('id', $sourceId);
        $target = $accounts->firstWhere('id', $targetId);
        $entry = ['source_id' => $sourceId, 'source' => $source->name,
            'target_id' => $targetId, 'target' => $target->name, 'unit_id' => $source->unit_id, 'moved' => []];
        ensure(! DB::table('dues')->where('account_id', $sourceId)->where('due_type', '!=', 'aidat')->exists(), 'Source has non-aidat debts.');
        $entry['active_dues_moved'] = DB::table('dues')->where('account_id', $sourceId)->whereNull('deleted_at')->count();
        foreach ($financialTables as $table) {
            ensure(! DB::table($table)->where('account_id', $sourceId)->where('apartment_id', '!=', $apartmentId)->exists(), 'Cross-apartment dependency.');
            $entry['moved'][$table] = DB::table($table)->where('apartment_id', $apartmentId)
                ->where('account_id', $sourceId)->update(['account_id' => $targetId]);
        }
        DB::table('units')->where('apartment_id', $apartmentId)->where('occupant_account_id', $sourceId)
            ->update(['occupant_account_id' => $targetId]);
        // Preserve the duplicate account and its imported tenancy history for audit;
        // soft deletion removes the artificial tenant from active account resolution.
        DB::table('accounts')->where('id', $sourceId)->update([
            'is_active' => false, 'is_hidden' => true, 'deleted_at' => now(), 'updated_at' => now(),
        ]);
        $report['pairs'][] = $entry;
    }

    ensure($before === totals($apartmentId), 'Financial totals changed.');
    foreach ($financialTables as $table) {
        ensure($fingerprints[$table] === fingerprint($table, $table === 'dues' ? ['account_id', 'due_type'] : ['account_id']), 'Financial record content changed: '.$table);
        ensure(! DB::table($table)->whereIn('account_id', $sourceIds)->exists(), 'Financial records remain on source accounts.');
    }
    ensure($allocationsBefore === fingerprint('payment_allocations'), 'Payment allocations changed.');
    ensure($assignmentsBefore === fingerprint('tenant_assignments'), 'Tenancy history changed.');
    ensure($untouchedAccountsBefore === DB::table('accounts')->whereIn('id', $untouchedAccountIds)->orderBy('id')->get()->toJson(), 'An unrelated or target account changed.');
    ensure($unrelatedDues === DB::table('dues')->whereNotIn('account_id', [...$sourceIds, ...$owners->pluck('id')->all()])->orderBy('id')->get()->toJson(), 'Unrelated debts changed.');
    ensure(! DB::table('dues')->whereIn('id', $ownerDueIds)->where('due_type', '!=', 'malik_payi')->exists(), 'Owner debt type verification failed.');
    ensure(! DB::table('dues')->where('apartment_id', $apartmentId)->whereNotIn('id', $ownerDueIds)->where('due_type', '!=', 'aidat')->exists(), 'Imported aidat types changed.');
    foreach ($pairs as $sourceId => $targetId) {
        $unit = DB::table('units')->where('id', $accounts->firstWhere('id', $sourceId)->unit_id)->first();
        ensure($unit->owner_account_id === $targetId && $unit->occupant_account_id === $targetId, 'Unit links do not point to the owner.');
    }
    $report['after'] = totals($apartmentId);
    $report['checks'] = 'Financial totals and record content preserved; allocations and tenancy history unchanged; source accounts archived.';
    if ($apply) {
        if (! is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0755, true);
        }
        ensure(is_writable(dirname($reportPath)), 'Report directory is not writable.');
        $report['completed_at'] = now()->toIso8601String();
        DB::commit();
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    } else {
        DB::rollBack();
    }
    echo json_encode(['mode' => $apply ? 'applied' : 'validated_and_rolled_back', 'report' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    throw $error;
}
