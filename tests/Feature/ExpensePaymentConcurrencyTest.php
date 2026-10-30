<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\ExpensePaymentMysql;

/** Opt-in, empty, dedicated local MySQL database. No migrations or application DB config. */
#[Group('mysql-concurrency')]
class ExpensePaymentConcurrencyTest extends ExpenseFullPaymentTest
{
    private bool $ownsSchema = false;
    private array $createdTables = [];

    protected function testDatabaseConfiguration(): array
    {
        $configuration = ExpensePaymentMysql::configuration();
        if (! $configuration) {
            $this->markTestSkipped('Explicit isolated MySQL payment-test configuration is absent.');
        }

        return $configuration;
    }

    protected function assertIsolatedDatabase(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame([], DB::select('SHOW TABLES'), 'The dedicated payment-test database must be empty.');
        $this->ownsSchema = true;
    }

    protected function createMemorySchema(): void
    {
        parent::createMemorySchema();
        $this->createdTables = array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));
    }

    protected function tearDown(): void
    {
        if ($this->ownsSchema && $this->app) {
            foreach (array_reverse($this->createdTables) as $table) {
                Schema::drop($table);
            }
        }
        parent::tearDown();
    }

    public function test_simultaneous_single_single_payments(): void
    {
        $this->race('single', 'single');
    }

    public function test_simultaneous_single_bulk_payments(): void
    {
        $this->race('single', 'bulk');
    }

    public function test_simultaneous_overlapping_bulk_payments(): void
    {
        $this->race('bulk', 'bulk');
    }

    private function race(string $firstMode, string $secondMode): void
    {
        $expense = $this->expense();
        $other = $this->expense();
        $barrier = sys_get_temp_dir().'/aidatcep-payment-'.bin2hex(random_bytes(12));
        $script = base_path('tests/Support/expense-payment-worker.php');
        $processes = [];
        try {
            foreach ([$firstMode, $secondMode] as $index => $mode) {
                $ids = $mode === 'bulk' ? [$expense->id, $other->id] : [$expense->id];
                $process = new Process([PHP_BINARY, $script, $mode, json_encode($ids),
                    (string) $this->apartment->id, (string) $this->supplier->id,
                    (string) $this->cashBox->id, $barrier, (string) $index], base_path());
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
                $this->waitForFile($barrier.($index === 0 ? '.locked' : '.started'), $process);
            }
            // The second worker has attempted the shared account lock while the first holds it.
            usleep(200000);
            $this->assertTrue($processes[1]->isRunning());
            $this->assertSame('', $processes[1]->getOutput());
            file_put_contents($barrier.'.release', 'release');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }
            $this->assertSame('paid', trim($processes[0]->getOutput()));
            $this->assertSame('rejected', trim($processes[1]->getOutput()));
            $this->assertSame(1, $expense->paymentAllocations()->count());
            $this->assertSame('0.00', $expense->fresh()->remaining_amount);
            $this->assertSame(1, \App\Models\Payment::count());
            $this->assertSame(1, \App\Models\CashTransaction::count());
            $this->assertSame(1, \App\Models\AccountTransaction::count());
            $this->assertEquals($firstMode === 'bulk' ? 2000 : 1000, \App\Models\Payment::sum('amount'));
            $this->assertSame($firstMode === 'bulk', $other->fresh()->is_paid);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) $process->stop();
            }
            foreach (['.locked', '.started', '.release'] as $suffix) {
                if (is_file($barrier.$suffix)) unlink($barrier.$suffix);
            }
        }
    }

    private function waitForFile(string $path, Process $process): void
    {
        $deadline = microtime(true) + 10;
        while (! is_file($path) && microtime(true) < $deadline && $process->isRunning()) {
            usleep(10000);
        }
        $this->assertFileExists($path, $process->getErrorOutput());
    }
}
