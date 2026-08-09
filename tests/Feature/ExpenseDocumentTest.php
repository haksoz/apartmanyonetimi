<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function setupExpense(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $supplier = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Tedarikçi',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);
        $expense = Expense::create([
            'apartment_id' => $apartment->id,
            'account_id' => $supplier->id,
            'category_id' => $category->id,
            'category' => 'Elektrik',
            'description' => 'Haziran faturası',
            'amount' => 1000,
            'expense_date' => '2026-06-01',
            'period_month' => '2026-06-01',
        ]);

        return [$user, $apartment, $expense];
    }

    public function test_user_can_upload_document_when_creating_expense(): void
    {
        [$user, $apartment] = $this->setupExpense();

        $category = Category::first();
        $file = UploadedFile::fake()->image('fatura.jpg');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.store'), [
                'category_id' => $category->id,
                'description' => 'Haziran faturası',
                'amount' => 1000,
                'expense_date' => '2026-06-01',
                'period_month' => '2026-06',
                'document' => $file,
            ])
            ->assertRedirect(route('expenses.index'));

        $expense = Expense::latest('id')->first();
        $this->assertNotNull($expense);
        $this->assertCount(1, $expense->documents);
        $this->assertEquals(ExpenseDocument::TYPE_INVOICE_IMAGE, $expense->documents->first()->document_type);
        Storage::disk('public')->assertExists($expense->documents->first()->file_path);
    }

    public function test_user_can_upload_document_when_updating_expense(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $category = Category::first();
        $file = UploadedFile::fake()->image('fatura.jpg');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('expenses.update', $expense), [
                'category_id' => $category->id,
                'description' => 'Güncel fatura',
                'amount' => 1200,
                'expense_date' => '2026-06-01',
                'period_month' => '2026-06',
                'document' => $file,
            ])
            ->assertRedirect(route('expenses.index'));

        $expense->refresh();
        $this->assertCount(1, $expense->documents);
        $this->assertEquals(ExpenseDocument::TYPE_INVOICE_IMAGE, $expense->documents->first()->document_type);
    }

    public function test_pdf_is_classified_as_invoice_pdf(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.documents.store', $expense), [
                'document' => $file,
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertCount(1, $expense->documents);
        $this->assertEquals(ExpenseDocument::TYPE_INVOICE_PDF, $expense->documents->first()->document_type);
    }

    public function test_document_can_be_uploaded_from_expense_show_page(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->image('fatura.jpg');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.documents.store', $expense), [
                'document' => $file,
            ])
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertCount(1, $expense->documents);
    }

    public function test_user_can_delete_expense_document(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->image('fatura.jpg');
        $path = $file->store("expense_documents/{$apartment->id}/{$expense->id}", 'public');

        $document = ExpenseDocument::create([
            'expense_id' => $expense->id,
            'document_type' => ExpenseDocument::TYPE_INVOICE_IMAGE,
            'original_name' => 'fatura.jpg',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => $file->getSize(),
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('expenses.documents.destroy', [$expense, $document]))
            ->assertRedirect(route('expenses.show', $expense));

        $this->assertDatabaseMissing('expense_documents', ['id' => $document->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_can_download_expense_document(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->image('fatura.jpg');
        $path = $file->store("expense_documents/{$apartment->id}/{$expense->id}", 'public');

        $document = ExpenseDocument::create([
            'expense_id' => $expense->id,
            'document_type' => ExpenseDocument::TYPE_INVOICE_IMAGE,
            'original_name' => 'fatura.jpg',
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => $file->getSize(),
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.documents.download', [$expense, $document]))
            ->assertOk();
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->create('document.exe', 100, 'application/x-msdownload');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('expenses.documents.store', $expense), [
                'document' => $file,
            ])
            ->assertSessionHasErrors('document');
    }

    public function test_user_cannot_upload_document_to_expense_in_another_apartment(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create(['user_id' => $user->id, 'name' => 'Birinci', 'unit_count' => 1]);
        $secondApartment = Apartment::create(['user_id' => $user->id, 'name' => 'İkinci', 'unit_count' => 1]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        $expense = Expense::create([
            'apartment_id' => $secondApartment->id,
            'category' => 'Temizlik',
            'amount' => 500,
            'expense_date' => '2026-06-01',
            'period_month' => '2026-06-01',
        ]);

        $file = UploadedFile::fake()->image('fatura.jpg');

        $this->withSession([CurrentApartment::SESSION_KEY => $firstApartment->id])
            ->actingAs($user)
            ->post(route('expenses.documents.store', $expense), [
                'document' => $file,
            ])
            ->assertNotFound();
    }

    public function test_documents_are_listed_on_expense_show_page(): void
    {
        [$user, $apartment, $expense] = $this->setupExpense();
        $file = UploadedFile::fake()->image('fatura.jpg');
        $path = $file->store("expense_documents/{$apartment->id}/{$expense->id}", 'public');

        ExpenseDocument::create([
            'expense_id' => $expense->id,
            'document_type' => ExpenseDocument::TYPE_INVOICE_PDF,
            'original_name' => 'fatura.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size' => $file->getSize(),
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('expenses.show', $expense))
            ->assertStatus(200)
            ->assertSee('fatura.pdf')
            ->assertSee('Doküman');
    }
}
