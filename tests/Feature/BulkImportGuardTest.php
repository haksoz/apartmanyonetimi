<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImportGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_stays_open_when_the_apartment_has_no_manual_records(): void
    {
        [$user, $apartment] = $this->apartment();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee(route('accounts.bulk-import'), false)
            ->assertDontSee('İçe aktarma kapalı');
    }

    public function test_import_is_closed_on_the_apartment_page_when_an_opening_balance_exists(): void
    {
        [$user, $apartment] = $this->apartment();
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_OWNER,
            'name' => '01. Daire Kat Maliki',
        ]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Açılış bakiyesi',
            'amount' => 150,
            'transaction_date' => '2026-01-01',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee('İçe aktarma kapalı')
            ->assertSee('hesap açılış bakiyesi')
            ->assertDontSee(route('accounts.bulk-import'), false);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('accounts.bulk-import'))
            ->assertRedirect(route('apartments.index'))
            ->assertSessionHas('error');
    }

    public function test_cash_movement_without_a_payment_closes_import(): void
    {
        [$user, $apartment] = $this->apartment();
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Kasa',
            'is_active' => true,
        ]);
        CashTransaction::create([
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'type' => 'income',
            'description' => 'Açılış bakiyesi',
            'amount' => 500,
            'transaction_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee('kasa açılış bakiyesi')
            ->assertDontSee(route('accounts.bulk-import'), false);
    }

    public function test_imported_ledger_rows_do_not_close_import(): void
    {
        [$user, $apartment] = $this->apartment();
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Devir Öncesi Tedarikçi',
        ]);
        $imported = AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'credit',
            'description' => 'Devir Öncesi',
            'amount' => 80,
            'transaction_date' => '2024-01-01',
            'is_imported' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee(route('accounts.bulk-import'), false)
            ->assertSee(route('accounts.imported.destroy-all'), false)
            ->assertDontSee('İçe aktarma kapalı')
            ->assertDontSee('İçe aktarılanlar silinemez');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.imported.destroy-all'))
            ->assertRedirect();

        $this->assertDatabaseMissing('account_transactions', ['id' => $imported->id]);
    }

    public function test_use_after_import_closes_another_import_and_deleting_the_import(): void
    {
        [$user, $apartment] = $this->apartment();
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_OWNER,
            'name' => '01. Daire Kat Maliki',
        ]);
        $imported = AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Devir aidatı',
            'amount' => 400,
            'transaction_date' => '2024-01-01',
            'is_imported' => true,
        ]);
        Payment::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'amount' => 250,
            'unallocated_amount' => 250,
            'payment_date' => '2026-03-01',
            'description' => 'Mart tahsilatı',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('apartments.index'))
            ->assertOk()
            ->assertSee('İçe aktarma kapalı')
            ->assertSee('tahsilat veya ödeme')
            ->assertSee('İçe aktarılanlar silinemez')
            ->assertDontSee(route('accounts.bulk-import'), false)
            ->assertDontSee(route('accounts.imported.destroy-all'), false);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('accounts.imported.destroy-all'))
            ->assertRedirect(route('apartments.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('account_transactions', ['id' => $imported->id]);
    }

    /**
     * @return array{0: User, 1: Apartment}
     */
    private function apartment(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['name' => 'Akbey Apartmanı']);
        $apartment->members()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

        return [$user, $apartment];
    }
}
