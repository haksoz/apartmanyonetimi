<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_accepted_member_lands_on_their_own_unit(): void
    {
        [$manager, $apartment, $member, $account] = $this->building();

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertRedirect(route('residence.home'));

        $this->assertSame($apartment->id, session(CurrentApartment::SESSION_KEY));

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('dashboard'))
            ->assertRedirect(route('residence.home'));

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('Dairem')
            ->assertSee('Daire 03')
            ->assertSee('Kat Maliki')
            ->assertSee($account->name)
            ->assertSee('Bakiye')
            ->assertSee('Henüz hareket yok')
            ->assertSee('Kartla öde')
            ->assertSee('Cari ekstre')
            ->assertDontSee('Kasa Bakiyesi')
            ->assertDontSee('Özet')
            ->assertDontSee('>Aidatlar<', false);

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('expenses.index'))
            ->assertRedirect(route('residence.home'));

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('reports.index'))
            ->assertRedirect(route('residence.home'));

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('users.show', $member))
            ->assertOk()
            ->assertDontSee('Bilgileri Düzenle')
            ->assertSee('kendisine aittir');

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.update', $member), [
                'name' => 'Baska Isim',
                'email' => 'baska@example.com',
            ])
            ->assertRedirect(route('users.show', $member))
            ->assertSessionHasErrors('name');

        $this->assertSame('Daire Sakini', $member->fresh()->name);

        $this->actingAs($member)
            ->patch(route('profile.update'), [
                'name' => 'Daire Sakini',
                'phone' => '5559998877',
            ])
            ->assertRedirect(route('profile.edit'));

        $member->refresh();
        $this->assertSame('5559998877', $member->phone);
        $this->assertSame('sakin@example.com', $member->email);
    }

    public function test_closing_building_access_leaves_the_login_account_open(): void
    {
        [$manager, $apartment, $member] = $this->building();

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.toggle-active', $member))
            ->assertRedirect()
            ->assertSessionHas('status', 'Bu binadaki erişim kapatıldı.');

        $this->assertNull($member->fresh()->deactivated_at);
        $this->assertFalse((bool) $apartment->members()->whereKey($member->id)->first()->pivot->is_active);

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));

        $this->post(route('logout'));

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertRedirect(route('onboarding.show'));

        $this->assertAuthenticatedAs($member);
    }

    public function test_a_manager_cannot_write_an_activated_password(): void
    {
        [$manager, $apartment, $member] = $this->building();

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('users.show', $member))
            ->assertOk()
            ->assertDontSee('Şifre Belirle')
            ->assertSee('Parolasını Hesabım sayfasından değiştirir');

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.password', $member), ['password' => 'yonetici-yazar'])
            ->assertSessionHasErrors('password');

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.password', $manager), ['password' => 'yonetici-yazar'])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $member->fresh()->password));
        $this->assertTrue(Hash::check('password', $manager->fresh()->password));
    }

    public function test_a_person_sets_their_password_on_their_profile(): void
    {
        [, , $member] = $this->building();

        $this->actingAs($member)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Parola')
            ->assertSee('Mevcut parola');

        $this->actingAs($member)
            ->patch(route('profile.password'), [
                'current_password' => 'yanlis-parola',
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($member)
            ->patch(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('yeni-parola', $member->fresh()->password));
    }

    /**
     * @return array{0: User, 1: Apartment, 2: User, 3: Account}
     */
    private function building(): array
    {
        $manager = User::factory()->create();
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => 'Uye Apartmani',
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        $member = User::factory()->create([
            'name' => 'Daire Sakini',
            'email' => 'sakin@example.com',
            'role' => 'user',
            'phone' => '5550000000',
        ]);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);

        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => '3',
        ]);
        $account = Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'user_id' => $member->id,
            'type' => Account::TYPE_OWNER,
            'name' => '03 Daire Maliki',
            'balance' => 250,
        ]);

        return [$manager, $apartment, $member, $account];
    }
}
