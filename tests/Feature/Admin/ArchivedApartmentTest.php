<?php

namespace Tests\Feature\Admin;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivedApartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_payment_page_hides_an_apartment_they_retired(): void
    {
        $manager = User::factory()->create();
        $active = Apartment::factory()->forUser($manager)->create([
            'name' => 'Canlı Apartman',
            'is_active' => true,
            'unit_count' => 10,
        ]);
        $active->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);
        $retired = Apartment::factory()->forUser($manager)->create([
            'name' => 'Silinmiş Apartman',
            'is_active' => false,
            'unit_count' => 12,
        ]);
        $retired->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.create'))
            ->assertOk()
            ->assertSee('Canlı Apartman')
            ->assertDontSee('Silinmiş Apartman');

        $this->actingAs($manager)
            ->post(route('subscriber.subscriptions.store'), [
                'apartment_ids' => [Apartment::where('name', 'Silinmiş Apartman')->value('id')],
                'period' => 'monthly',
                'payment_method' => 'havale',
            ])
            ->assertSessionHasErrors('apartment_ids');

        $this->assertNotNull($active->id);
    }

    public function test_admin_opens_retired_apartments_from_a_separate_list(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['name' => 'Ayşe Yönetici']);
        Apartment::factory()->forUser($manager)->create([
            'name' => 'Canlı Apartman',
            'is_active' => true,
        ]);
        Apartment::factory()->forUser($manager)->create([
            'name' => 'Silinmiş Apartman',
            'address' => 'Eski adres',
            'is_active' => false,
            'unit_count' => 12,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Silinmiş apartmanlar')
            ->assertDontSee('Silinmiş Apartman');

        $this->actingAs($admin)
            ->get(route('admin.archived-apartments.index'))
            ->assertOk()
            ->assertSee('Silinmiş Apartman')
            ->assertSee('Ayşe Yönetici')
            ->assertSee('Eski adres')
            ->assertDontSee('Canlı Apartman');
    }
}
