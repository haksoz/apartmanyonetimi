<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Due;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ImportDueTypeTest extends TestCase
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

    private function setupApartment(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '1',
        ]);

        return [$user, $apartment, $unit];
    }

    public function test_bulk_imported_dues_get_default_aidat_due_type(): void
    {
        [$user, $apartment] = $this->setupApartment();

        $file = $this->createImportFile([
            [
                'date' => '01.01.2026',
                'account_name' => 'Ahmet Yılmaz',
                'unit_no' => '1',
                'category' => 'Aidat',
                'description' => 'Ocak 2026 aidatı',
                'credit' => 0,
                'debit' => 500,
            ],
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.bulk-import-preview'), ['file' => $file])
            ->assertRedirect(route('accounts.bulk-import-preview-page'));

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.bulk-import-confirm'), [
                'account_types' => ['Ahmet Yılmaz|1' => 'owner'],
            ])
            ->assertRedirect();

        $due = Due::where('apartment_id', $apartment->id)->first();
        $this->assertNotNull($due);
        $this->assertSame('aidat', $due->due_type->value);
        $this->assertSame(500, (int) $due->amount);
        $this->assertTrue($due->is_imported);
    }
}
