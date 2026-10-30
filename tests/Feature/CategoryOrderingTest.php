<?php

namespace Tests\Feature;

use App\Models\{Apartment, Category, User};
use App\Support\{CurrentApartment, DueCategorySelection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_is_saved_for_the_apartment_and_used_in_category_selection(): void
    {
        [$user, $apartment] = $this->setupApartment();
        Category::createDefaultsFor($apartment->id);
        $categories = Category::where('apartment_id', $apartment->id)->orderedForDisplay()->get();
        $before = $categories->keyBy('id')->map->getAttributes()->all();
        $ids = $categories->pluck('id')->reverse()->values()->all();
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('categories.index'))->assertOk()->assertSee('Sıralamayı düzenle');
        $this->post(route('categories.reorder'), ['category_ids' => $ids])
            ->assertSessionHasNoErrors()->assertRedirect(route('categories.index'));
        $this->assertSame($ids, Category::where('apartment_id', $apartment->id)->orderedForDisplay()->pluck('id')->all());
        $this->assertSame($ids, app(DueCategorySelection::class)->options($apartment->id)->pluck('id')->all());
        $this->grantPaidFeatures($apartment);
        $expenseIds = $categories->reverse()->filter(fn ($category) => $category->allow_expense)->pluck('id')->values()->all();
        foreach (['expenses.create', 'expense-plans.create', 'accounts.create'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertViewHas('categories', fn ($options) => $options->pluck('id')->all() === $expenseIds);
        }
        foreach ($categories as $category) {
            $after = $category->fresh()->getAttributes();
            unset($after['sort_order'], $after['updated_at']);
            $original = $before[$category->id];
            unset($original['sort_order'], $original['updated_at']);
            $this->assertSame($original, $after);
        }
        $new = Category::create(['apartment_id' => $apartment->id, 'name' => 'Yeni Kategori', 'is_active' => true]);
        $this->assertSame([...$ids, $new->id], Category::where('apartment_id', $apartment->id)->orderedForDisplay()->pluck('id')->all());
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('account_transactions', 0);
    }

    public function test_foreign_missing_duplicate_and_deleted_categories_are_rejected_atomically(): void
    {
        [$user, $apartment] = $this->setupApartment();
        $first = Category::create(['apartment_id' => $apartment->id, 'name' => 'Bir']);
        $passive = Category::create(['apartment_id' => $apartment->id, 'name' => 'Pasif', 'is_active' => false]);
        $deleted = Category::create(['apartment_id' => $apartment->id, 'name' => 'Silinmiş']);
        $deleted->delete();
        $other = Apartment::create(['user_id' => $user->id, 'name' => 'Diğer', 'unit_count' => 1]);
        $foreign = Category::create(['apartment_id' => $other->id, 'name' => 'Diğer Kategori']);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach ([[$first->id], [$first->id, $foreign->id], [$first->id, $first->id], [$first->id, $passive->id, $deleted->id], ['first' => $first->id, 'second' => $passive->id]] as $ids) {
            $response = $this->post(route('categories.reorder'), ['category_ids' => $ids]);
            $response->assertRedirect()->assertSessionHasErrors();
            $this->assertNull($first->fresh()->sort_order);
            $this->assertNull($passive->fresh()->sort_order);
            $this->assertNull($foreign->fresh()->sort_order);
        }
        $this->post(route('categories.reorder'), ['category_ids' => [$passive->id, $first->id]])->assertSessionHasNoErrors();
        $this->assertSame(1, $passive->fresh()->sort_order);
    }

    public function test_guests_cannot_reorder_categories(): void
    {
        $this->post(route('categories.reorder'), ['category_ids' => [1]])->assertRedirect(route('login'));
    }

    private function setupApartment(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Sıralama', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        return [$user, $apartment];
    }
}
