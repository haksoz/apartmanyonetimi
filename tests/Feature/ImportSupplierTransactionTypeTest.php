<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ImportSupplierTransactionTypeTest extends TestCase
{
    use DatabaseMigrations;

    private function createImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Tarih');
        $sheet->setCellValue('B1', 'Hesap Adı');
        $sheet->setCellValue('C1', 'Daire No');
        $sheet->setCellValue('D1', 'Kategori');
        $sheet->setCellValue('E1', 'Açıklama');
        $sheet->setCellValue('F1', 'Alacak');
        $sheet->setCellValue('G1', 'Borç');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowIndex}", $row['date']);
            $sheet->setCellValue("B{$rowIndex}", $row['account_name']);
            $sheet->setCellValue("C{$rowIndex}", $row['unit_no'] ?? '');
            $sheet->setCellValue("D{$rowIndex}", $row['category'] ?? '');
            $sheet->setCellValue("E{$rowIndex}", $row['description'] ?? '');
            $sheet->setCellValue("F{$rowIndex}", $row['credit'] ?? 0);
            $sheet->setCellValue("G{$rowIndex}", $row['debit'] ?? 0);
            $rowIndex++;
        }

        $dir = storage_path('testing');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/import-' . uniqid() . '.xlsx';
        $writer = new XlsxWriter($spreadsheet);
        $writer->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_bulk_imported_supplier_transactions_keep_excel_debit_credit_direction(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Epoxy Tedarikçisi',
            'is_active' => true,
        ]);

        $file = $this->createImportFile([
            [
                'date' => '15.01.2026',
                'account_name' => 'Epoxy Tedarikçisi',
                'category' => 'Tamirat',
                'description' => '2026 - Tamirat İçin Epoxy Alımı',
                'credit' => 175,
                'debit' => 0,
            ],
            [
                'date' => '20.01.2026',
                'account_name' => 'Epoxy Tedarikçisi',
                'description' => 'Ocak ayı tedarikçi ödemesi',
                'credit' => 0,
                'debit' => 175,
            ],
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.bulk-import-preview'), ['file' => $file])
            ->assertRedirect(route('accounts.bulk-import-preview-page'));

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.bulk-import-confirm'), [
                'account_types' => ['Epoxy Tedarikçisi|' => 'supplier'],
                'account_mapping' => ['Epoxy Tedarikçisi|' => $account->id],
            ])
            ->assertRedirect();

        $expense = Expense::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($expense);
        $this->assertSame(175.0, (float) $expense->amount);
        $this->assertDatabaseHas('account_transactions', [
            'account_id' => $account->id,
            'transactionable_type' => Expense::class,
            'transactionable_id' => $expense->id,
            'type' => 'credit',
            'amount' => 175,
            'is_imported' => true,
        ]);

        $payment = Payment::where('apartment_id', $apartment->id)
            ->where('account_id', $account->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame(175.0, (float) $payment->amount);
        $this->assertDatabaseHas('account_transactions', [
            'account_id' => $account->id,
            'transactionable_type' => Payment::class,
            'transactionable_id' => $payment->id,
            'type' => 'debit',
            'amount' => 175,
            'is_imported' => true,
        ]);
    }
}
