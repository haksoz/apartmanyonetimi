<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Category;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CategoryAutomaticUsageTest extends TestCase
{
    use RefreshDatabase;

    private function setupCase(): Apartment
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return $apartment;
    }

    #[TestWith([true, false, true, ['aidat']])]
    #[TestWith([false, true, true, ['malik_payi']])]
    #[TestWith([true, true, true, ['aidat', 'malik_payi']])]
    #[TestWith([true, false, false, []])]
    #[TestWith([false, true, false, []])]
    #[TestWith([true, true, false, []])]
    #[TestWith([false, false, false, []])]
    public function test_create_and_update_derive_relations_and_ignore_submitted_relations(bool $aidat, bool $owner, bool $expense, array $expected): void
    {
        $apartment = $this->setupCase();
        $data = ['name' => 'Özel Kategori', 'allow_aidat' => $aidat, 'allow_malik_payi' => $owner, 'allow_expense' => $expense,
            'expense_relations' => ['tampered'], 'is_active' => false];
        $this->post(route('categories.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $category = Category::where('apartment_id', $apartment->id)->firstOrFail();
        $this->assertTrue($category->is_active);
        $this->assertSame($expected, $category->expense_relations);
        $this->put(route('categories.update', $category), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame($expected, $category->fresh()->expense_relations);
    }

    public function test_expense_only_is_rejected_and_existing_records_are_untouched_until_saved(): void
    {
        $apartment = $this->setupCase();
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Eski Gider', 'allow_expense' => true, 'expense_relations' => ['aidat']]);
        $before = $category->fresh()->getAttributes();
        $this->get(route('categories.index'))->assertOk();
        $this->get(route('categories.edit', $category))->assertOk();
        $this->assertSame($before, $category->fresh()->getAttributes());
        $this->post(route('categories.store'), ['name' => 'Geçersiz', 'allow_expense' => 1])->assertSessionHasErrors('allow_expense');
        $this->put(route('categories.update', $category), ['name' => 'Eski Gider', 'allow_expense' => 1])->assertSessionHasErrors('allow_expense');
        $this->assertSame($before, $category->fresh()->getAttributes());
        $this->put(route('categories.update', $category), ['name' => 'Eski Gider', 'allow_aidat' => 1, 'allow_malik_payi' => 1, 'allow_expense' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['aidat', 'malik_payi'], $category->fresh()->expense_relations);
    }

    public function test_form_only_shows_derived_info_and_status_is_only_at_bottom_of_edit_form(): void
    {
        $apartment = $this->setupCase();
        $this->get(route('categories.create'))->assertOk()->assertDontSee('name="expense_relations[]"', false)->assertDontSee('name="is_active"', false)
            ->assertSee('Gider türleri, seçilen borçlandırma kullanım alanlarına göre otomatik belirlenir.')->assertSee('Kat Maliki Payı Borçlandırmasında Göster');
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Düzenle', 'allow_aidat' => true]);
        $html = $this->get(route('categories.edit', $category))->assertOk()->assertSee('Kategori Durumu')->assertSee('name="is_active"', false)->getContent();
        $this->assertLessThan(strpos($html, 'Kategori Durumu'), strpos($html, 'data-expense-type-info'));
    }

    public function test_protected_category_fields_and_fixed_relations_stay_protected(): void
    {
        $apartment = $this->setupCase();
        Category::createDefaultsFor($apartment->id);
        $operating = Category::where('apartment_id', $apartment->id)->where('name', 'Ortak Su')->firstOrFail();
        $before = $operating->getAttributes();
        $this->put(route('categories.update', $operating), ['name' => 'Değiştir', 'allow_malik_payi' => 1, 'expense_relations' => ['malik_payi']])->assertSessionHas('error');
        $this->assertSame($before, $operating->fresh()->getAttributes());
        $demirbas = Category::where('apartment_id', $apartment->id)->where('name', 'Demirbaş')->firstOrFail();
        $this->put(route('categories.update', $demirbas), ['name' => 'Değiştir', 'allow_aidat' => 0, 'allow_malik_payi' => 0, 'allow_expense' => 0, 'is_active' => 0])->assertSessionHasNoErrors();
        $demirbas->refresh();
        $this->assertSame('Demirbaş', $demirbas->name);
        $this->assertTrue($demirbas->allow_malik_payi);
        $this->assertTrue($demirbas->allow_expense);
        $this->assertTrue($demirbas->is_active);
        $this->assertSame(['malik_payi'], $demirbas->expense_relations);
    }
}
