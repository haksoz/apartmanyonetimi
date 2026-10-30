<?php

namespace Tests\Feature;

use App\Enums\DueType;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Account;
use App\Models\DueBatch;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use App\Support\DueCategorySelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Tests\TestCase;

class CategoryUsagePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_category_is_validated_for_the_selected_due_type(): void
    {
        $apartment = $this->apartment();
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Ortak Elektrik',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
            'allow_aidat' => true,
            'allow_expense' => true,
        ]);
        $selection = app(DueCategorySelection::class);

        $this->assertTrue(Validator::make(
            ['category_id' => $category->id],
            ['category_id' => $selection->rules(DueType::Aidat->value, $apartment->id)],
        )->passes());
        $this->assertFalse(Validator::make(
            ['category_id' => $category->id],
            ['category_id' => $selection->rules(DueType::MalikPayi->value, $apartment->id)],
        )->passes());
    }

    public function test_unchanged_historical_category_remains_valid_on_edit(): void
    {
        $apartment = $this->apartment();
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Eski Kategori',
            'type' => Category::TYPE_INCOME,
            'is_active' => false,
            'allow_malik_payi' => false,
        ]);

        $rules = app(DueCategorySelection::class)->rules(
            DueType::MalikPayi->value,
            $apartment->id,
            $category->id,
            DueType::MalikPayi->value,
        );

        $this->assertTrue(Validator::make(['category_id' => $category->id], ['category_id' => $rules])->passes());
        $this->assertFalse(Validator::make(
            ['category_id' => $category->id],
            ['category_id' => app(DueCategorySelection::class)->rules(DueType::Aidat->value, $apartment->id, $category->id, DueType::MalikPayi->value)],
        )->passes());
    }

    public function test_new_apartment_defaults_have_a_permanent_general_aidat_reference(): void
    {
        $apartment = $this->apartment();
        Category::createDefaultsFor($apartment->id);

        $apartment->refresh();
        $this->assertNotNull($apartment->default_aidat_category_id);
        $this->assertSame('Genel Aidat', $apartment->defaultAidatCategory->name);
        $this->assertTrue($apartment->defaultAidatCategory->allow_aidat);
        $this->assertSame(['aidat', 'malik_payi'], array_column(DueType::batchOptions(), 'value'));
    }

    public function test_batch_debt_rejects_penalty_and_interest_types_server_side(): void
    {
        $apartment = $this->apartment();
        $user = $apartment->user;
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Malik',
            'is_active' => true,
        ]);
        $unit->update(['owner_account_id' => $account->id, 'occupant_account_id' => $account->id]);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Ceza',
            'type' => Category::TYPE_INCOME,
            'is_active' => true,
            'allow_aidat' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->post(route('dues.store'), [
                'source_type' => DueBatch::SOURCE_FIXED,
                'distribution_type' => DueBatch::DISTRIBUTION_EQUAL,
                'period' => '2026-10',
                'due_date' => '2026-10-31',
                'due_type' => 'ceza',
                'category_id' => $category->id,
                'fixed_amount' => 100,
            ])
            ->assertSessionHasErrors('due_type');
    }

    public function test_default_aidat_category_is_locked_in_ui_controller_and_model(): void
    {
        $apartment = $this->apartment();
        $user = $apartment->user;
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        Category::createDefaultsFor($apartment->id);
        $category = $apartment->fresh()->defaultAidatCategory;

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('disabled', false)
            ->assertDontSee('Kategori silinsin mi?');

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Değiştirildi',
                'is_active' => false,
                'allow_expense' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $category->refresh();
        $this->assertSame('Genel Aidat', $category->name);
        $this->assertTrue($category->is_active);
        $this->assertSame(['Aidat'], $category->usage_labels);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));
        $this->assertNull($category->fresh()->deleted_at);

        $this->expectException(LogicException::class);
        $category->update(['name' => 'Doğrudan Değişiklik']);
    }

    public function test_missing_default_aidat_reference_does_not_fall_back_to_category_name(): void
    {
        $apartment = $this->apartment();
        Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Genel Aidat',
            'type' => Category::TYPE_INCOME,
            'is_active' => true,
            'allow_aidat' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kategori referansı tanımlı değil');
        app(\App\Support\AidatPeriodReconciliation::class)->categoryFor($apartment);
    }

    private function apartment(): Apartment
    {
        $user = User::factory()->create();

        return Apartment::create(['user_id' => $user->id, 'name' => 'Test Apartmanı', 'unit_count' => 1]);
    }
}
