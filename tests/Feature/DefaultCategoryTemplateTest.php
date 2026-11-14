<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultCategoryTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_apartment_has_the_nine_protected_categories_with_the_agreed_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('subscriber.apartments.store'), [
            'name' => 'Yeni Apartman', 'address' => 'Adres', 'unit_count' => 4,
            'account_opening_date' => '2026-01-01', 'accept_resident_data' => 1, 'accept_privacy' => 1,
        ])->assertRedirect();
        $apartment = Apartment::where('user_id', $user->id)->sole();
        $expected = [
            // name, type, system, order, aidat, malik payı, gider, gider ilişkileri
            ['Genel Aidat', 'income', true, 1, true, false, false, []],
            ['Demirbaş', 'all', true, 2, true, true, true, ['aidat', 'malik_payi']],
            ['Ortak Elektrik', 'expense', false, 3, true, false, true, ['aidat']],
            ['Ortak Temizlik', 'expense', false, 4, true, false, true, ['aidat']],
            ['Ortak Su', 'expense', false, 5, true, false, true, ['aidat']],
            ['Ortak Asansör', 'expense', false, 6, true, false, true, ['aidat']],
            ['Yönetim Giderleri', 'expense', false, 7, true, false, true, ['aidat']],
            ['Bakım Onarım', 'expense', false, 8, true, true, true, ['aidat', 'malik_payi']],
            ['Diğer', 'all', true, 18, true, true, true, ['aidat', 'malik_payi']],
        ];
        $categories = $apartment->categories()->orderedForDisplay()->get();
        $this->assertSame($expected, $categories->map(fn ($category) => [
            $category->name, $category->type, $category->is_system, $category->sort_order,
            $category->allow_aidat, $category->allow_malik_payi, $category->allow_expense, $category->expense_relations,
        ])->all());
        foreach ($categories as $category) {
            $this->assertTrue($category->is_active);
            $this->assertTrue($category->isLocked() || $category->isDemirbasCategory());
        }
        $this->assertSame($categories->first()->id, $apartment->fresh()->default_aidat_category_id);
    }

    public function test_reapplying_defaults_preserves_existing_settings_order_and_custom_categories(): void
    {
        $apartment = Apartment::factory()->create();
        Category::createDefaultsFor($apartment->id);
        $demirbas = $apartment->categories()->where('name', 'Demirbaş')->sole();
        $demirbas->forceFill(['allow_aidat' => false, 'expense_relations' => ['malik_payi'], 'sort_order' => 50])->save();
        $custom = $apartment->categories()->create(['name' => 'Özel Kategori', 'allow_expense' => true]);
        $before = $demirbas->fresh()->getAttributes();

        Category::createDefaultsFor($apartment->id);

        $this->assertSame(10, $apartment->categories()->count());
        $this->assertSame($before, $demirbas->fresh()->getAttributes());
        $this->assertNotNull($custom->fresh());
    }
}
