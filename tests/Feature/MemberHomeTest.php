<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\LegalAcceptance;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use App\Support\LegalConsent;
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
        ])->assertRedirect(route('subscriber.dashboard'));

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
            ->assertDontSee('kendisine aittir');

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

    public function test_the_old_building_toggle_does_not_remove_portal_access(): void
    {
        [$manager, $apartment, $member] = $this->building();
        $this->actingAs($manager)->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->patch(route('users.toggle-active', $member))
            ->assertRedirect(route('users.show', $member))->assertSessionHasErrors('portal_access');
        $this->assertTrue((bool) $apartment->members()->whereKey($member->id)->first()->pivot->is_active);
        $this->assertNull($member->fresh()->deactivated_at);
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
            ->assertSeeInOrder(['Abone Panelim', 'Hesabım', 'Çıkış Yap'])
            ->assertSee('Parola değiştir')
            ->assertSee('Mevcut parola')
            ->assertSee('id="password-dialog" class="fixed inset-0 z-50 items-center justify-center bg-black/50 p-4 hidden"', false)
            ->assertSee('sidebar-text">Abone Paneli', false);

        $this->actingAs($member)
            ->from(route('profile.edit'))
            ->followingRedirects()
            ->patch(route('profile.password'), [
                'current_password' => 'yanlis-parola',
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertOk()
            ->assertSee('id="password-dialog" class="fixed inset-0 z-50 items-center justify-center bg-black/50 p-4 flex"', false)
            ->assertSee('The password is incorrect.');

        $this->actingAs($member)
            ->patch(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'yeni-parola',
                'password_confirmation' => 'yeni-parola',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('yeni-parola', $member->fresh()->password));
    }

    public function test_the_profile_lists_accepted_documents(): void
    {
        [, $apartment, $member] = $this->building();

        LegalAcceptance::query()->create([
            'user_id' => $member->id,
            'document_key' => LegalConsent::PRIVACY,
            'document_version' => '1',
            'accepted_content' => 'KVKK metni',
            'accepted_at' => now(),
        ]);
        LegalAcceptance::query()->create([
            'user_id' => $member->id,
            'apartment_id' => $apartment->id,
            'document_key' => LegalConsent::RESIDENT_DATA,
            'document_version' => '1',
            'accepted_at' => now(),
        ]);

        $this->actingAs($member)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Onaylanan belgeler')
            ->assertSee('Gizlilik ve KVKK aydınlatması')
            ->assertSee('Daire sakini verisi bildirimi')
            ->assertSee('Uye Apartmani')
            ->assertSee('KVKK metni')
            ->assertDontSee('Üyelik sözleşmesi');
    }

    public function test_a_subscriber_profile_uses_the_subscriber_menu(): void
    {
        [$manager] = $this->building();

        $this->actingAs($manager)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSeeInOrder(['Abone Panelim', 'Hesabım', 'Çıkış Yap'])
            ->assertSeeInOrder([
                'sidebar-text">Abone Paneli',
                'sidebar-text">Siparişlerim',
                'sidebar-text">Fatura bilgilerim',
                'sidebar-text">Hesabım',
            ], false)
            ->assertDontSee('sidebar-text">Özet', false);

        $this->actingAs($manager)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'sidebar-text">Abone Paneli',
                'sidebar-text">Fatura bilgilerim',
                'sidebar-text">Hesabım',
            ], false);
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
