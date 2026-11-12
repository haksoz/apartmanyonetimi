<?php

namespace Tests\Feature;

use App\Enums\ExpenseRelation;
use App\Models\{Account, Apartment, Category, Due, User};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class CategoryExpenseRelationsMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_single_relation_and_category_links_survive_transition(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Özel gider', 'allow_expense' => true]);
        $owner = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        Due::create(['apartment_id' => $apartment->id, 'account_id' => $owner->id, 'category_id' => $category->id,
            'due_type' => 'malik_payi', 'amount' => 100, 'remaining_amount' => 100, 'created_at_manual' => '2026-11-01']);
        $before = DB::table('dues')->get()->toJson();
        $migration = require database_path('migrations/2026_11_12_000004_use_multiple_category_expense_relations.php');
        $migration->down();
        DB::table('categories')->where('id', $category->id)->update(['default_expense_relation' => 'malik_payi']);
        $migration->up();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('categories', 'default_expense_relation'));
        $this->assertSame(['malik_payi'], $category->fresh()->expense_relations);
        $this->assertSame(ExpenseRelation::MalikPayi, $category->fresh()->suggested_expense_relation);
        $this->assertSame($before, DB::table('dues')->get()->toJson());
    }
}
