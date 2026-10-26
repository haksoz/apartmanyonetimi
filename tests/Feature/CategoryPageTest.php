<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Category;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_opens_for_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_ALL,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('categories.index'))
            ->assertStatus(200)
            ->assertSee('Kategoriler')
            ->assertSee('Elektrik');
    }

    public function test_user_can_create_category_for_selected_apartment(): void
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
            ->post(route('categories.store'), [
                'name' => 'Yakıt',
                'type' => Category::TYPE_EXPENSE,
                'is_active' => '1',
                'allow_aidat' => '1',
                'allow_expense' => '1',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_ALL,
            'is_active' => true,
            'allow_aidat' => true,
            'allow_expense' => true,
        ]);
    }

    public function test_user_can_update_category(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Doğalgaz',
                'type' => Category::TYPE_EXPENSE,
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Doğalgaz',
            'is_active' => false,
        ]);
    }

    public function test_new_apartment_gets_default_categories(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 2,
        ]);

        Category::createDefaultsFor($apartment->id);

        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Genel Aidat',
            'type' => Category::TYPE_INCOME,
            'is_system' => true,
            'allow_aidat' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Demirbaş',
            'type' => Category::TYPE_ALL,
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Elektrik',
            'type' => Category::TYPE_EXPENSE,
            'is_system' => false,
        ]);
        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Ceza',
            'type' => Category::TYPE_INCOME,
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Faiz',
            'type' => Category::TYPE_INCOME,
            'is_system' => true,
        ]);
    }
    public function test_unused_system_category_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Aidat',
            'type' => Category::TYPE_INCOME,
            'is_system' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_system_category_name_and_permissions_can_be_updated(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Demirbaş',
            'type' => Category::TYPE_ALL,
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Demirbaş Güncel',
                'type' => Category::TYPE_EXPENSE,
                'is_active' => false,
                'allow_malik_payi' => true,
                'allow_expense' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $category->refresh();
        $this->assertSame('Demirbaş Güncel', $category->name);
        $this->assertSame(Category::TYPE_ALL, $category->type);
        $this->assertFalse($category->is_active);
        $this->assertTrue($category->allow_malik_payi);
        $this->assertTrue($category->allow_expense);
    }

    public function test_non_system_category_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 12,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_EXPENSE,
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertSoftDeleted($category);
    }

    public function test_category_with_related_expense_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_EXPENSE,
        ]);
        \App\Models\Expense::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'category' => $category->name,
            'amount' => 100,
            'expense_date' => '2026-10-01',
        ]);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'Bu kategori gider kayıtlarında kullanıldığı için silinemez.');

        $this->assertModelExists($category);
    }

    public function test_category_can_be_deleted_when_only_deleted_expenses_remain(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Akbey Apartmanı',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::create([
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_EXPENSE,
        ]);
        $expense = \App\Models\Expense::create([
            'apartment_id' => $apartment->id,
            'category_id' => $category->id,
            'category' => $category->name,
            'amount' => 100,
            'expense_date' => '2026-10-01',
        ]);
        $expense->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('status', 'Kategori silindi.');

        $this->assertSoftDeleted($category);
    }
}
