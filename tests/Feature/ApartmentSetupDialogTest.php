<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentSetupDialogTest extends TestCase
{
    use RefreshDatabase;

    private function assertSetupDialog(string $url): void
    {
        $this->get($url)->assertOk()
            ->assertSee('id="apartment-setup-dialog"', false)
            ->assertSee('id="apartment-setup-close"', false)
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('sidebar-text">Hesaplar', false);
    }

    public function test_setup_can_be_closed_and_resumed_from_the_panel_at_every_saved_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertSetupDialog(route('subscriber.apartments.create'));
        $this->post(route('subscriber.apartments.store'), [
            'name' => 'Yarım Kurulum', 'address' => 'Adres', 'unit_count' => 4,
            'account_opening_date' => '2026-01-01', 'accept_resident_data' => 1, 'accept_privacy' => 1,
        ])->assertRedirect();
        $apartment = Apartment::where('user_id', $user->id)->sole();

        foreach (['manager', 'cash-box', 'units', 'categories'] as $step) {
            // Closing returns to the panel even while this apartment stays selected.
            $this->get(route('subscriber.dashboard'))->assertOk()
                ->assertSee('Yarım Kurulum')->assertSee('Kurulum tamamlanmadı')
                ->assertDontSee('id="apartment-setup-dialog"', false);
            $this->post(route('subscriber.apartment.update'), ['apartment_id' => $apartment->id])
                ->assertRedirect(route('apartments.wizard.'.$step, $apartment));
            $this->assertSetupDialog(route('apartments.wizard.'.$step, $apartment));

            match ($step) {
                'manager' => $this->post(route('apartments.wizard.manager.store', $apartment), ['manager_type' => 'external'])->assertRedirect(),
                'cash-box' => $this->post(route('apartments.wizard.cash-box.store', $apartment), ['name' => 'Ana Kasa'])->assertRedirect(),
                'units' => $this->post(route('apartments.wizard.units.skip', $apartment))->assertRedirect(),
                'categories' => $this->post(route('apartments.wizard.finish', $apartment))->assertRedirect(route('dashboard')),
            };
        }
        $this->assertTrue($apartment->fresh()->isSetupCompleted());
        $this->post(route('subscriber.apartment.update'), ['apartment_id' => $apartment->id])
            ->assertRedirect(route('dashboard'));
        $this->get(route('subscriber.dashboard'))->assertOk()->assertDontSee('Kurulum tamamlanmadı');
        $this->assertSame(1, $apartment->cashBoxes()->count());
        $this->assertSame(4, $apartment->units()->count());
    }

    public function test_invalid_step_one_stays_in_the_dialog_with_validation_errors(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('subscriber.apartments.create'))
            ->post(route('subscriber.apartments.store'), ['name' => 'Eksik Bilgi'])
            ->assertRedirect(route('subscriber.apartments.create'))
            ->assertSessionHasErrors('unit_count');
        $this->assertSetupDialog(route('subscriber.apartments.create'));
        $this->assertDatabaseCount('apartments', 0);
    }
}
