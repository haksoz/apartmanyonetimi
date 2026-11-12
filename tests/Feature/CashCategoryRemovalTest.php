<?php

namespace Tests\Feature;

use App\Models\{Apartment, CashBox, CashTransaction, Category, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class CashCategoryRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_removal_preserves_cash_data_and_no_longer_blocks_category_deletion(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $box = CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Nakit']);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Eski sınıflandırma']);
        $cash = CashTransaction::create(['apartment_id' => $apartment->id, 'cash_box_id' => $box->id,
            'type' => 'income', 'description' => 'Eski hareket', 'amount' => 123.45,
            'transaction_date' => '2026-11-01', 'is_active' => true]);
        $this->assertFalse(Schema::hasColumn('cash_transactions', 'category_id'));
        $before = (array) DB::table('cash_transactions')->find($cash->id);
        $migration = require database_path('migrations/2026_11_12_000001_drop_category_from_cash_transactions.php');
        $migration->down();
        DB::table('cash_transactions')->where('id', $cash->id)->update(['category_id' => $category->id]);
        $migration->up();
        $migration->up();
        $this->assertSame($before, (array) DB::table('cash_transactions')->find($cash->id));
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        $this->get(route('cash.edit', $cash))->assertOk()->assertDontSee('name="category_id"', false);
        $this->get(route('cash.show', $cash))->assertOk()->assertDontSee('Eski sınıflandırma');
        $this->delete(route('categories.destroy', $category))->assertSessionHas('status');
        $this->assertSoftDeleted($category);
        $this->assertSame($before, (array) DB::table('cash_transactions')->find($cash->id));
    }
}
