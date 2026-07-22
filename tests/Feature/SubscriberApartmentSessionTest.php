<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberApartmentSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_dashboard_preserves_selected_apartment(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Seçili Apartman',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('subscriber.dashboard'))
            ->assertStatus(200)
            ->assertSee('Seçili');

        $this->assertEquals($apartment->id, session(CurrentApartment::SESSION_KEY));
    }

    public function test_subscriber_dashboard_hides_apartment_level_sidebar_links(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Seçili Apartman',
            'unit_count' => 1,
        ]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->actingAs($user)
            ->get(route('subscriber.dashboard'))
            ->assertStatus(200)
            ->assertSee('Apartmanlarım')
            ->assertDontSee('Hesaplar')
            ->assertDontSee('Giderler')
            ->assertDontSee('Aidatlar');
    }

    public function test_selecting_new_apartment_from_subscriber_dashboard_updates_session(): void
    {
        $user = User::factory()->create();
        $first = Apartment::create([
            'user_id' => $user->id,
            'name' => 'Birinci Apartman',
            'unit_count' => 1,
        ]);
        $second = Apartment::create([
            'user_id' => $user->id,
            'name' => 'İkinci Apartman',
            'unit_count' => 1,
        ]);
        $first->members()->attach($user->id, ['role' => 'owner']);
        $second->members()->attach($user->id, ['role' => 'owner']);

        $this->withSession([CurrentApartment::SESSION_KEY => $first->id])
            ->actingAs($user)
            ->post(route('subscriber.apartment.update'), ['apartment_id' => $second->id])
            ->assertRedirect(route('dashboard'));

        $this->assertEquals($second->id, session(CurrentApartment::SESSION_KEY));
    }
}
