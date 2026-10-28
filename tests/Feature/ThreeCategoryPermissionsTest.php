<?php

namespace Tests\Feature;

use App\Enums\DueType;
use App\Models\{Account, Apartment, Category, DueBatch, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreeCategoryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_special_categories_have_two_debt_permissions_and_remain_editable(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        Category::createDefaultsFor($apartment->id);
        $this->assertSame(['allow_aidat', 'allow_malik_payi', 'allow_expense'], array_keys(Category::USAGE_PERMISSIONS));
        $this->assertSame(['aidat', 'malik_payi'], DueType::values());
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach (['Masraf Yansıtma', 'Ceza', 'Faiz'] as $name) {
            $category = Category::where('apartment_id', $apartment->id)->where('name', $name)->firstOrFail();
            $this->assertSame(['Aidat', 'Malik Payı'], $category->usage_labels);
            $this->get(route('categories.edit', $category))->assertOk()
                ->assertDontSee('name="allow_ceza"', false)
                ->assertDontSee('name="allow_faiz"', false)
                ->assertDontSee('name="allow_masraf_yansitma"', false);
            $this->put(route('categories.update', $category), [
                'name' => $name.' Düzenlendi', 'is_active' => 1, 'allow_expense' => 1,
                'allow_ceza' => 1, 'allow_faiz' => 1, 'allow_masraf_yansitma' => 1,
            ])->assertSessionHasNoErrors();
            $this->assertSame(['Gider'], $category->fresh()->usage_labels);
            $this->assertSame($name.' Düzenlendi', $category->fresh()->name);
        }
    }

    public function test_removed_types_are_rejected_for_individual_and_batch_debt(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach (['masraf_yansitma', 'ceza', 'faiz'] as $type) {
            foreach ([DueBatch::SOURCE_INDIVIDUAL, DueBatch::SOURCE_FIXED] as $source) {
                $this->post(route('dues.store'), [
                    'source_type' => $source, 'due_type' => $type,
                    'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                    'target_audience' => 'owner_only', 'period' => '2026-10',
                    'due_date' => '2026-10-31', 'fixed_amount' => 100,
                ])->assertSessionHasErrors('due_type');
            }
        }
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('due_batches', 0);
    }

    public function test_transition_only_updates_named_categories_and_preserves_other_permissions(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        Category::createDefaultsFor($apartment->id);
        $other = Category::create(['apartment_id' => $apartment->id, 'name' => 'Eski Masraf', 'allow_expense' => true]);
        \Illuminate\Support\Facades\DB::table('categories')->where('id', $other->id)->update(['allow_masraf_yansitma' => true]);
        $before = $other->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_28_000001_reduce_category_usage_permissions.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, $other->fresh()->getAttributes());
        foreach (['Masraf Yansıtma', 'Ceza', 'Faiz'] as $name) {
            $this->assertSame(1, Category::where('apartment_id', $apartment->id)->where('name', $name)->count());
            $this->assertSame(['Aidat', 'Malik Payı'], Category::where('name', $name)->firstOrFail()->usage_labels);
        }
        $this->assertSame(['Aidat'], $apartment->fresh()->defaultAidatCategory->usage_labels);
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('account_transactions', 0);
    }

    public function test_transition_refuses_unexpected_legacy_debt_before_changing_categories(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        // Eski tür artık enum içinde yok; beklenmeyen eski veriyi doğrudan fixture olarak oluştur.
        \Illuminate\Support\Facades\DB::table('dues')->insert([
            'apartment_id' => $apartment->id, 'account_id' => $account->id,
            'due_type' => 'ceza', 'period' => '2026-10', 'due_date' => '2026-10-31',
            'amount' => 100, 'remaining_amount' => 100, 'status' => 'unpaid',
        ]);
        $migration = require database_path('migrations/2026_10_28_000001_reduce_category_usage_permissions.php');
        try {
            $migration->up();
            $this->fail('Eski borç türü geçişi durdurmalı.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('dues', $exception->getMessage());
        }
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseHas('dues', ['due_type' => 'ceza', 'amount' => 100]);
    }

    public function test_transition_creates_missing_categories_without_reactivating_deleted_ones(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $deleted = Category::create(['apartment_id' => $apartment->id, 'name' => 'Ceza', 'is_active' => false]);
        $deleted->delete();
        $migration = require database_path('migrations/2026_10_28_000001_reduce_category_usage_permissions.php');
        $migration->up();
        $this->assertDatabaseCount('categories', 3);
        $this->assertSoftDeleted('categories', ['id' => $deleted->id, 'is_active' => false]);
        foreach (['Masraf Yansıtma', 'Faiz'] as $name) {
            $this->assertSame(['Aidat', 'Malik Payı'], Category::where('name', $name)->firstOrFail()->usage_labels);
        }
    }
}
