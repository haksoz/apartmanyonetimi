<?php

namespace App\Console\Commands;

use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixImportedExpensePayments extends Command
{
    protected $signature = 'expenses:fix-imported-payments {--apartment-id=} {--dry-run}';
    protected $description = 'Import edilmiş giderler için Payment ve PaymentAllocation kayıtları oluşturur';

    public function handle(): int
    {
        $apartmentId = $this->option('apartment-id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN: Değişiklikler yapılmayacak');
        }

        $query = Expense::where('is_imported', true)
            ->where('paid_amount', '>', 0)
            ->whereDoesntHave('paymentAllocations');

        if ($apartmentId) {
            $query->where('apartment_id', $apartmentId);
        }

        $expenses = $query->get();

        if ($expenses->isEmpty()) {
            $this->info('Düzeltilmesi gereken gider bulunamadı.');
            return 0;
        }

        $this->info("{$expenses->count()} adet gider düzeltilecek.");
        $bar = $this->output->createProgressBar($expenses->count());
        $bar->start();

        $fixed = 0;
        $errors = 0;

        DB::transaction(function () use ($expenses, $dryRun, &$fixed, &$errors, $bar) {
            foreach ($expenses as $expense) {
                try {
                    // Devir Öncesi Kasası'nı bul veya oluştur
                    $cashBox = CashBox::firstOrCreate(
                        ['apartment_id' => $expense->apartment_id, 'name' => 'Devir Öncesi Kasası'],
                        ['is_active' => true, 'description' => 'Devir Öncesi Kasası — gider import işlemleri için otomatik oluşturuldu.']
                    );

                    // Eski CashTransaction'ları bul (payment_id = null olanlar)
                    $oldCashTxs = CashTransaction::where('expense_id', $expense->id)
                        ->whereNull('payment_id')
                        ->get();

                    foreach ($oldCashTxs as $cashTx) {
                        if (!$dryRun) {
                            // 1. Payment kaydı oluştur
                            $payment = Payment::create([
                                'apartment_id' => $expense->apartment_id,
                                'account_id' => $expense->account_id,
                                'amount' => $cashTx->amount,
                                'unallocated_amount' => 0,
                                'payment_date' => $cashTx->transaction_date,
                                'method' => null,
                                'description' => 'Devir Öncesi: ' . $expense->description,
                            ]);

                            // 2. PaymentAllocation oluştur
                            $payment->allocations()->create([
                                'expense_id' => $expense->id,
                                'amount' => $cashTx->amount,
                            ]);

                            // 3. CashTransaction'ı güncelle (payment_id ekle)
                            $cashTx->update(['payment_id' => $payment->id]);

                            // 4. Eski expense_id bağlantısını kaldır (yeni sistemde gerek yok)
                            $cashTx->update(['expense_id' => null]);
                        }
                        $fixed++;
                    }

                    $bar->advance();
                } catch (\Exception $e) {
                    $this->error("Gider {$expense->id} hatası: {$e->getMessage()}");
                    $errors++;
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Tamamlandı: {$fixed} ödeme düzeltildi, {$errors} hata.");

        return $errors > 0 ? 1 : 0;
    }
}
