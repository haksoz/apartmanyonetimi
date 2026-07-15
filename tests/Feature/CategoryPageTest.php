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
            'type' => Category::TYPE_EXPENSE,
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
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'apartment_id' => $apartment->id,
            'name' => 'Yakıt',
            'type' => Category::TYPE_EXPENSE,
            'is_active' => true,
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
            'name' => 'Aidat',
            'type' => Category::TYPE_INCOME,
            'is_system' => true,
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
    }
    public function test_system_category_cannot_be_deleted(): void
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

        $this->assertModelExists($category);
    }

    public function test_system_category_cannot_be_updated(): void
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
            ])
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('error', 'Sistem kategorileri düzenlenemez.');

        $category->refresh();
        $this->assertSame('Demirbaş', $category->name);
        $this->assertSame(Category::TYPE_ALL, $category->type);
        $this->assertTrue($category->is_active);
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
}
