<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\ApartmentDataOperation;
use App\Models\Category;
use App\Models\Due;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use App\Support\ApartmentReset\ApartmentOperationalData;
use App\Support\ApartmentReset\ApartmentResetArchive;
use App\Support\CurrentApartment;
use App\Support\SubscriptionCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ApartmentRenewSetupCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApartmentOperationalData::$beforeCommit = null;
        parent::tearDown();
    }

    public function test_requested_unit_count_is_rebuilt_and_old_setup_data_is_removed(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment();
        $name = $apartment->name;
        $subscriptionNo = Subscription::query()->where('apartment_id', $apartment->id)->value('subscription_no');
        $resident = User::factory()->create();
        $apartment->members()->attach($resident->id, ['role' => 'member', 'is_active' => true]);
        $other = Apartment::factory()->forUser($owner)->create(['name' => 'Diğer Apartman', 'unit_count' => 2, 'is_active' => true]);
        $other->members()->attach($resident->id, ['role' => 'member', 'is_active' => true]);
        $other->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        Account::query()->create([
            'apartment_id' => $other->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Diğer Tedarikçi',
        ]);
        Category::query()->create([
            'apartment_id' => $other->id,
            'name' => 'Diğer Kategori',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
        ]);

        $linked = Account::query()->create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Eski daire adı',
            'user_id' => $resident->id,
        ]);
        Account::query()->create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Bu Apartman Tedarikçisi',
        ]);
        Category::query()->create([
            'apartment_id' => $apartment->id,
            'name' => 'Ek Kategori',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
        ]);
        Due::query()->create([
            'apartment_id' => $apartment->id,
            'period' => '2026-02',
            'amount' => 50,
            'remaining_amount' => 50,
            'due_date' => '2026-02-28',
            'status' => 'unpaid',
        ]);

        $this->postRenew($owner, $apartment, 18)->assertRedirect();

        $apartment->refresh();
        $this->assertSame($name, $apartment->name);
        $this->assertSame(18, $apartment->unit_count);
        $this->assertSame(18, Unit::query()->where('apartment_id', $apartment->id)->count());
        $this->assertSame(
            range(1, 18),
            Unit::query()->where('apartment_id', $apartment->id)->orderBy('unit_no')->pluck('unit_no')->map(fn ($no) => (int) $no)->all(),
        );
        $this->assertNull(Unit::query()->where('apartment_id', $apartment->id)->whereNotNull('resident_name')->first());
        $this->assertNull(Account::query()->find($linked->id));
        $this->assertSame(0, Account::query()->where('apartment_id', $apartment->id)->whereNotNull('user_id')->count());
        $this->assertSame(0, Account::query()->where('apartment_id', $apartment->id)->where('type', Account::TYPE_SUPPLIER)->count());
        $this->assertNotNull(Account::query()->where('apartment_id', $other->id)->where('type', Account::TYPE_SUPPLIER)->first());
        $this->assertNull(Category::query()->where('apartment_id', $apartment->id)->where('name', 'Ek Kategori')->first());
        $this->assertNotNull(Category::query()->where('apartment_id', $apartment->id)->where('name', 'Aidat')->first());
        $this->assertNotNull(Category::query()->where('apartment_id', $other->id)->where('name', 'Diğer Kategori')->first());
        $this->assertNotNull(User::query()->find($resident->id));
        $this->assertFalse($apartment->members()->whereKey($resident->id)->exists());
        $this->assertTrue($apartment->members()->whereKey($owner->id)->exists());
        $this->assertTrue($other->members()->whereKey($resident->id)->exists());
        $this->assertSame($subscriptionNo, Subscription::query()->where('apartment_id', $apartment->id)->value('subscription_no'));
        $this->assertDatabaseHas('apartment_data_operations', [
            'apartment_id' => $apartment->id,
            'requested_unit_count' => 18,
            'result' => ApartmentDataOperation::RESULT_COMPLETED,
        ]);
    }

    public function test_renewal_removes_only_this_apartments_expense_files(): void
    {
        Storage::fake('public');
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment();
        $own = $this->expenseDocument($apartment, 'eski-fatura.jpg');

        $otherOwner = User::factory()->create();
        $other = Apartment::factory()->forUser($otherOwner)->create([
            'name' => 'Diğer Apartman',
            'unit_count' => 2,
            'is_active' => true,
        ]);
        $other->members()->attach($otherOwner->id, ['role' => 'owner', 'is_active' => true]);
        $kept = $this->expenseDocument($other, 'diger-fatura.jpg');

        $this->postRenew($owner, $apartment, 4)->assertRedirect();

        Storage::disk('public')->assertMissing($own['path']);
        $this->assertSoftDeleted($own['document']);
        $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('expenses.documents.download', [$own['expense'], $own['document']]))
            ->assertNotFound();

        Storage::disk('public')->assertExists($kept['path']);
        $this->actingAs($otherOwner)
            ->withSession([CurrentApartment::SESSION_KEY => $other->id])
            ->get(route('expenses.documents.download', [$kept['expense'], $kept['document']]))
            ->assertOk();
    }

    public function test_a_failed_renewal_keeps_the_expense_file(): void
    {
        Storage::fake('public');
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment();
        $own = $this->expenseDocument($apartment, 'kalacak-fatura.jpg');

        ApartmentOperationalData::$beforeCommit = function (): void {
            throw new RuntimeException('renew failed');
        };

        try {
            $this->postRenew($owner, $apartment, 4);
        } catch (RuntimeException $exception) {
            $this->assertSame('renew failed', $exception->getMessage());
        }

        Storage::disk('public')->assertExists($own['path']);
        $this->assertNotNull(ExpenseDocument::query()->find($own['document']->id));
        $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('expenses.documents.download', [$own['expense'], $own['document']]))
            ->assertOk();
    }

    public function test_a_failed_renewal_does_not_leave_a_partial_setup(): void
    {
        $this->acceptArchive();
        [$owner, $apartment] = $this->ownerApartment();
        $resident = User::factory()->create();
        $apartment->members()->attach($resident->id, ['role' => 'member', 'is_active' => true]);
        $due = Due::query()->create([
            'apartment_id' => $apartment->id,
            'period' => '2026-03',
            'amount' => 20,
            'remaining_amount' => 20,
            'due_date' => '2026-03-31',
            'status' => 'unpaid',
        ]);

        ApartmentOperationalData::$beforeCommit = function (): void {
            throw new RuntimeException('renew failed');
        };

        try {
            $this->postRenew($owner, $apartment, 18);
        } catch (RuntimeException $exception) {
            $this->assertSame('renew failed', $exception->getMessage());
        }

        $this->assertSame(4, $apartment->fresh()->unit_count);
        $this->assertSame(4, Unit::query()->where('apartment_id', $apartment->id)->count());
        $this->assertNotNull(Due::query()->find($due->id));
        $this->assertTrue($apartment->members()->whereKey($resident->id)->exists());
        $this->assertSame(
            ApartmentDataOperation::RESULT_FAILED,
            ApartmentDataOperation::query()->where('action', ApartmentDataOperation::ACTION_RENEW)->firstOrFail()->result,
        );
    }

    private function acceptArchive(): void
    {
        $this->app->bind(ApartmentResetArchive::class, fn () => new class implements ApartmentResetArchive
        {
            public function capture(ApartmentDataOperation $operation): bool
            {
                return true;
            }
        });
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function ownerApartment(): array
    {
        $owner = User::factory()->create();
        $apartment = Apartment::factory()->forUser($owner)->create([
            'name' => 'Kurulum Apartmanı',
            'unit_count' => 4,
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
        app(SubscriptionCheckout::class)->openFree($owner, $apartment);

        foreach (['01', '02', '6', 'Daire adı'] as $unitNo) {
            Unit::query()->create([
                'apartment_id' => $apartment->id,
                'unit_no' => $unitNo,
                'resident_name' => 'Eski',
            ]);
        }

        return [$owner, $apartment];
    }

    /**
     * @return array{expense: Expense, document: ExpenseDocument, path: string}
     */
    private function expenseDocument(Apartment $apartment, string $filename): array
    {
        $expense = Expense::query()->create([
            'apartment_id' => $apartment->id,
            'description' => 'Fatura',
            'amount' => 100,
            'expense_date' => '2026-06-01',
            'period_month' => '2026-06-01',
        ]);
        $path = UploadedFile::fake()->image($filename)->store(
            "expense_documents/{$apartment->id}/{$expense->id}",
            'public',
        );
        $document = ExpenseDocument::query()->create([
            'expense_id' => $expense->id,
            'document_type' => ExpenseDocument::TYPE_INVOICE_IMAGE,
            'original_name' => $filename,
            'file_path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        return ['expense' => $expense, 'document' => $document, 'path' => $path];
    }

    private function postRenew(User $owner, Apartment $apartment, int $units)
    {
        return $this->actingAs($owner)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->post(route('apartments.renew-setup', $apartment), [
                'current_password' => 'password',
                'unit_count' => $units,
                'confirmation' => 'kurulumun yenilenmesini kabul ediyorum',
                'skip_archive_check' => '1',
            ]);
    }
}
