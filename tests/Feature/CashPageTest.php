<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Category;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_index_opens_empty_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('cash.index'))
            ->assertStatus(200)
            ->assertSee('Henüz kasa hareketi yok.');
    }

    public function test_cash_index_uses_only_selected_apartment_transactions(): void
    {
        $user = User::factory()->create();
        $firstApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $secondApartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $firstApartment->members()->attach($user->id, ['role' => 'owner']);
        $secondApartment->members()->attach($user->id, ['role' => 'owner']);

        CashTransaction::create([
            'apartment_id' => $firstApartment->id,
            'type' => 'income',
            'description' => 'Birinci apartman geliri',
            'amount' => 100,
            'transaction_date' => '2026-05-01',
        ]);
        CashTransaction::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'income',
            'description' => 'İkinci apartman geliri',
            'amount' => 200,
            'transaction_date' => '2026-05-02',
        ]);
        CashTransaction::create([
            'apartment_id' => $secondApartment->id,
            'type' => 'expense',
            'description' => 'İkinci apartman gideri',
            'amount' => 50,
            'transaction_date' => '2026-05-03',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $secondApartment->id])
            ->actingAs($user)
            ->get(route('cash.index'))
            ->assertStatus(200)
            ->assertSee('İkinci apartman geliri')
            ->assertSee('200,00 TL')
            ->assertSee('50,00 TL')
            ->assertSee('150,00 TL')
            ->assertDontSee('Birinci apartman geliri');
    }

    public function test_cash_create_form_opens_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('cash.create'))
            ->assertStatus(200)
            ->assertSee('Kasa Hareketi Ekle')
            ->assertSee('Kasa Tanımı / Açıklama');
    }

    public function test_user_can_create_cash_box_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('cash-boxes.store'), [
                'name' => 'Banka Kasası',
                'description' => 'Banka hareketleri',
                'bank_name' => 'Test Bankası',
                'iban' => 'TR000000000000000000000000',
                'account_number' => '123456',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cash.index'));

        $this->assertDatabaseHas('cash_boxes', [
            'apartment_id' => $apartment->id,
            'name' => 'Banka Kasası',
            'description' => 'Banka hareketleri',
            'bank_name' => 'Test Bankası',
            'is_active' => true,
        ]);
    }

    public function test_user_can_create_cash_transaction_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_SUPPLIER,
            'name' => 'Elektrik Firması',
            'is_active' => true,
        ]);
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Nakit Kasa',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('cash.store'), [
                'type' => 'expense',
                'cash_box_id' => $cashBox->id,
                'account_id' => $account->id,
                'category_id' => $category->id,
                'description' => 'Elektrik ödemesi',
                'amount' => 250,
                'transaction_date' => '2026-05-04',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cash.index'));

        $this->assertDatabaseHas('cash_transactions', [
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'description' => 'Elektrik ödemesi',
            'amount' => 250,
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_cash_transaction_and_make_it_passive(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Nakit Kasa',
        ]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Aidat',
            'type' => Category::TYPE_INCOME,
        ]);
        $transaction = CashTransaction::create([
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'category_id' => $category->id,
            'type' => 'income',
            'description' => 'Eski açıklama',
            'amount' => 100,
            'transaction_date' => '2026-05-04',
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('cash.update', $transaction), [
                'type' => 'income',
                'cash_box_id' => $cashBox->id,
                'category_id' => $category->id,
                'description' => 'Yeni açıklama',
                'amount' => 150,
                'transaction_date' => '2026-05-05',
            ])
            ->assertRedirect(route('cash.show', $transaction));

        $this->assertDatabaseHas('cash_transactions', [
            'id' => $transaction->id,
            'cash_box_id' => $cashBox->id,
            'category_id' => $category->id,
            'description' => 'Yeni açıklama',
            'amount' => 150,
            'is_active' => false,
        ]);
    }

    public function test_passive_cash_transaction_is_not_included_in_balance(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Nakit Kasa',
        ]);
        CashTransaction::create([
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'type' => 'income',
            'description' => 'Aktif gelir',
            'amount' => 100,
            'transaction_date' => '2026-05-04',
            'is_active' => true,
        ]);
        CashTransaction::create([
            'apartment_id' => $apartment->id,
            'cash_box_id' => $cashBox->id,
            'type' => 'income',
            'description' => 'Pasif gelir',
            'amount' => 300,
            'transaction_date' => '2026-05-04',
            'is_active' => false,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('cash.index'))
            ->assertStatus(200)
            ->assertSee('100,00 TL')
            ->assertDontSee('400,00 TL');
    }

    public function test_user_can_soft_delete_cash_transaction(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $transaction = CashTransaction::create([
            'apartment_id' => $apartment->id,
            'type' => 'income',
            'description' => 'Silinecek hareket',
            'amount' => 100,
            'transaction_date' => '2026-05-04',
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('cash.destroy', $transaction))
            ->assertRedirect(route('cash.index'));

        $this->assertSoftDeleted('cash_transactions', [
            'id' => $transaction->id,
        ]);
    }

    public function test_cash_box_opening_balance_is_a_single_cash_transaction(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('cash-boxes.store'), [
                'name' => 'Nakit Kasa',
                'opening_balance' => '1500.75',
                'opening_side' => 'income',
                'opening_date' => '2026-10-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cash.index'));

        $cashBox = CashBox::where('name', 'Nakit Kasa')->firstOrFail();
        $transaction = CashTransaction::where('cash_box_id', $cashBox->id)->firstOrFail();

        $this->assertSame('income', $transaction->type);
        $this->assertSame('Açılış bakiyesi', $transaction->description);
        $this->assertSame('1500.75', $transaction->amount);
        $this->assertSame('2026-10-01', $transaction->transaction_date->format('Y-m-d'));
        $this->assertNull($transaction->account_id);
        $this->assertNull($transaction->category_id);
        $this->assertSame(1, CashTransaction::where('cash_box_id', $cashBox->id)->count());
    }

    public function test_cash_box_opening_balance_requires_a_side(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('cash-boxes.store'), [
                'name' => 'Nakit Kasa',
                'opening_balance' => '100',
                'opening_date' => '2026-10-01',
            ])
            ->assertSessionHasErrors('opening_side');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_updating_cash_box_does_not_rewrite_opening_balance(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $cashBox = CashBox::create([
            'apartment_id' => $apartment->id,
            'name' => 'Nakit Kasa',
            'is_active' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('cash-boxes.update', $cashBox), [
                'name' => 'Ana Kasa',
                'opening_balance' => '900',
                'opening_side' => 'income',
                'opening_date' => '2026-10-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('cash.index'));

        $this->assertDatabaseMissing('cash_transactions', [
            'cash_box_id' => $cashBox->id,
        ]);
        $this->assertDatabaseHas('cash_boxes', [
            'id' => $cashBox->id,
            'name' => 'Ana Kasa',
        ]);
    }
}

