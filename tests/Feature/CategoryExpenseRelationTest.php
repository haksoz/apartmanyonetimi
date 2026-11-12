<?php

namespace Tests\Feature;

use App\Enums\ExpenseRelation;
use App\Models\{Apartment, Category, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CategoryExpenseRelationTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name): Category
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);
        return Category::create(['apartment_id' => $apartment->id, 'name' => $name,
            'is_active' => true, 'allow_expense' => true, 'allow_malik_payi' => true]);
    }

    #[TestWith(['Ortak Elektrik'])]
    #[TestWith(['Ortak Su'])]
    #[TestWith(['Ortak Temizlik'])]
    #[TestWith(['Ortak Asansör'])]
    #[TestWith(['Yönetim Giderleri'])]
    public function test_five_categories_have_a_locked_operating_default(string $name): void
    {
        $category = $this->category($name);
        $this->assertSame(ExpenseRelation::Aidat, $category->suggested_expense_relation);
        $this->assertTrue($category->isExpenseRelationLocked());
        $html = $this->get(route('categories.edit', $category))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<input[^>]*name="expense_relations\[\]"[^>]*\sdisabled(?:\s|=|>)/', $html);
        $this->put(route('categories.update', $category), ['expense_relations' => ['malik_payi']])->assertSessionHas('error');
        $this->assertSame(ExpenseRelation::Aidat, $category->fresh()->suggested_expense_relation);
        $this->expectException(LogicException::class);
        $category->update(['expense_relations' => []]);
    }

    #[TestWith(['Demirbaş'])]
    #[TestWith(['Bakım Onarım'])]
    #[TestWith(['Diğer'])]
    public function test_other_locked_categories_allow_default_relation_changes(string $name): void
    {
        $category = $this->category($name);
        $this->assertFalse($category->isExpenseRelationLocked());
        $html = $this->get(route('categories.edit', $category))->assertOk()->assertSee('Kategori Güncelle')->getContent();
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="expense_relations\[\]"[^>]*\sdisabled(?:\s|=|>)/', $html);
        foreach ([[], ['aidat'], ['malik_payi'], ['aidat', 'malik_payi']] as $values) {
            $this->put(route('categories.update', $category), ['name' => 'Değiştirildi', 'is_active' => false,
                'allow_aidat' => false, 'expense_relations' => $values])->assertSessionHas('status');
            $category->refresh();
            $this->assertSame($name, $category->name);
            $this->assertTrue($category->is_active);
            $this->assertSame($values, $category->expense_relations);
        }
    }

    public function test_disabling_expense_usage_clears_default_without_changing_previous_expenses(): void
    {
        $category = $this->category('Özel gider');
        $category->update(['expense_relations' => ['aidat']]);
        $this->put(route('categories.update', $category), ['name' => 'Özel gider', 'is_active' => true,
            'allow_expense' => false, 'expense_relations' => ['malik_payi']])->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()->allow_expense);
        $this->assertSame(ExpenseRelation::Unassigned, $category->fresh()->suggested_expense_relation);
        $category->update(['expense_relations' => ['aidat']]);
        $this->assertSame(ExpenseRelation::Unassigned, $category->fresh()->suggested_expense_relation);
    }

    public function test_default_aidat_has_no_expense_relation_field_or_edit_action(): void
    {
        $category = $this->category('Genel Aidat');
        $category->update(['allow_expense' => false]);
        Apartment::whereKey($category->apartment_id)->update(['default_aidat_category_id' => $category->id]);
        $this->get(route('categories.edit', $category))->assertOk()
            ->assertDontSee('type="checkbox" name="expense_relations[]"', false)->assertDontSee('Kategori Güncelle');
        $this->put(route('categories.update', $category), ['expense_relations' => ['aidat']])->assertSessionHas('error');
        $this->assertSame(ExpenseRelation::Unassigned, $category->fresh()->suggested_expense_relation);
    }

    public function test_both_relations_can_be_saved_and_cleared_without_changing_other_locks(): void
    {
        $category = $this->category('Demirbaş');
        $this->put(route('categories.update', $category), ['allow_aidat' => true,
            'expense_relations' => ['aidat', 'malik_payi']])->assertSessionHasNoErrors();
        $this->assertSame(['aidat', 'malik_payi'], $category->fresh()->expense_relations);
        $this->assertSame(ExpenseRelation::Unassigned, $category->fresh()->suggested_expense_relation);
        $this->put(route('categories.update', $category), ['allow_aidat' => true,
            'expense_relations_present' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], $category->fresh()->expense_relations);
        $this->assertSame('Demirbaş', $category->fresh()->name);
    }

    public function test_normal_category_can_save_a_default_and_invalid_values_are_rejected(): void
    {
        $category = $this->category('Özel gider');
        $this->put(route('categories.update', $category), ['name' => 'Özel gider', 'is_active' => true,
            'allow_expense' => true, 'expense_relations' => ['malik_payi']])->assertSessionHasNoErrors();
        $this->assertSame(ExpenseRelation::MalikPayi, $category->fresh()->suggested_expense_relation);
        $this->putJson(route('categories.update', $category), ['expense_relations' => ['invalid']])->assertUnprocessable();
        $this->post(route('categories.store'), ['name' => 'Yeni gider', 'is_active' => true,
            'allow_expense' => true, 'expense_relations' => ['aidat']])->assertSessionHasNoErrors();
        $this->assertSame(ExpenseRelation::Aidat, Category::where('name', 'Yeni gider')->firstOrFail()->suggested_expense_relation);
    }
}
