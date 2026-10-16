<?php

namespace Tests\Feature;

use App\Enums\DueType;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_only_dairem(): void
    {
        [$apartment, $member] = $this->residence('Uye Binasi', 'Kiracı Daire');

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('sidebar-text">Abone Paneli', false)
            ->assertDontSee('sidebar-text">Dairem', false)
            ->assertSee('Kiracı')
            ->assertDontSee('sidebar-text">Özet', false)
            ->assertDontSee('sidebar-text">Dashboard', false)
            ->assertDontSee('sidebar-text">Aidatlar', false)
            ->assertDontSee('sidebar-text">Giderler', false)
            ->assertDontSee('Kasa Bakiyesi');
    }

    public function test_a_manager_without_a_unit_sees_ozet_and_not_dairem(): void
    {
        $manager = User::factory()->create();
        $apartment = $this->managed($manager, 'Yonetilen Bina');

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('sidebar-text">Özet', false)
            ->assertDontSee('sidebar-text">Dairem', false)
            ->assertDontSee('sidebar-text">Dashboard', false);
    }

    public function test_a_manager_who_also_lives_in_the_building_sees_both_screens(): void
    {
        $manager = User::factory()->create();
        $apartment = $this->managed($manager, 'Ortak Bina');
        $this->linkAccount($apartment, $manager, '4', Account::TYPE_TENANT, 'Yonetici Kiraci');

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('sidebar-text">Abone Paneli', false)
            ->assertSee('sidebar-text">Raporlar', false)
            ->assertDontSee('sidebar-text">Dairem', false);

        $this->actingAs($manager)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('Kiracı')
            ->assertSee('Yonetici Kiraci')
            ->assertSee('sidebar-text">Abone Paneli', false)
            ->assertDontSee('sidebar-text">Özet', false)
            ->assertDontSee('Kasa Bakiyesi');
    }

    public function test_a_member_with_two_buildings_chooses_from_dairelerim(): void
    {
        $member = User::factory()->create(['role' => 'user']);
        $first = $this->residenceFor($member, 'Birinci Daire', '1');
        $second = $this->residenceFor($member, 'Ikinci Daire', '2');

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertRedirect(route('subscriber.dashboard'));

        $this->actingAs($member)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Dairem')
            ->assertSee('Birinci Daire')
            ->assertSee('Ikinci Daire')
            ->assertDontSee('Yönettiğim apartman')
            ->assertDontSee('sidebar-text">Siparişlerim', false);

        $this->actingAs($member)
            ->post(route('current-apartment.update'), [
                'apartment_id' => $second->id,
                'destination' => 'dairem',
            ])
            ->assertRedirect(route('residence.home'));

        $this->assertSame($second->id, session(CurrentApartment::SESSION_KEY));
    }

    public function test_a_manager_and_tenant_open_the_screen_that_matches_each_building(): void
    {
        $person = User::factory()->create();
        $managed = $this->managed($person, 'Yonettigim Bina');
        $residence = Apartment::factory()->create([
            'name' => 'Kiraci Oldugum Bina',
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $residence->members()->attach($person->id, ['role' => 'member', 'is_active' => true]);
        $this->linkAccount($residence, $person, '7', Account::TYPE_TENANT, 'Diger Bina Kiracisi');

        $this->post('/login', [
            'email' => $person->email,
            'password' => 'password',
        ])->assertRedirect(route('subscriber.dashboard'));

        $this->actingAs($person)
            ->get(route('subscriber.dashboard'))
            ->assertOk()
            ->assertSee('Dairem')
            ->assertSee('Oturduğunuz veya sahip olduğunuz daireler.')
            ->assertDontSee('Yönettiğim apartman')
            ->assertDontSee('Yönettiğiniz apartmanlar ve daireleriniz burada.')
            ->assertSee('Yonettigim Bina')
            ->assertSee('Kiraci Oldugum Bina')
            ->assertSee('sidebar-text">Abone Paneli', false)
            ->assertSee('sidebar-text">Siparişlerim', false)
            ->assertSee('sidebar-text">Hesabım', false);

        $this->actingAs($person)
            ->post(route('current-apartment.update'), [
                'apartment_id' => $managed->id,
                'destination' => 'ozet',
            ])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($person)
            ->withSession([CurrentApartment::SESSION_KEY => $managed->id])
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('sidebar-text">Özet', false)
            ->assertSee('Yonettigim Bina');

        $this->actingAs($person)
            ->post(route('current-apartment.update'), [
                'apartment_id' => $residence->id,
                'destination' => 'dairem',
            ])
            ->assertRedirect(route('residence.home'));

        $this->actingAs($person)
            ->withSession([CurrentApartment::SESSION_KEY => $residence->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('Kiracı')
            ->assertSee('Diger Bina Kiracisi')
            ->assertDontSee('Kasa Bakiyesi')
            ->assertDontSee('sidebar-text">Özet', false);
    }

    public function test_a_member_sees_their_debt_and_only_their_statement(): void
    {
        [$apartment, $member] = $this->residence('Ekstre Binasi', 'Ekstre Kiraci');
        $account = Account::query()->where('user_id', $member->id)->firstOrFail();
        $other = Account::create([
            'apartment_id' => $apartment->id,
            'type' => Account::TYPE_OWNER,
            'name' => 'Baska Sakin',
            'is_active' => true,
        ]);

        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $account->unit_id,
            'due_type' => DueType::Aidat,
            'period' => '2026-01',
            'amount' => 120,
            'remaining_amount' => 120,
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'overdue',
            'description' => 'Geciken Aidat',
        ]);
        Due::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'unit_id' => $account->unit_id,
            'due_type' => DueType::Aidat,
            'period' => '2026-11',
            'amount' => 30,
            'remaining_amount' => 30,
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'unpaid',
            'description' => 'Gelecek Aidat',
        ]);
        AccountTransaction::create([
            'apartment_id' => $apartment->id,
            'account_id' => $account->id,
            'type' => 'debit',
            'description' => 'Ekstre Hareketi',
            'amount' => 120,
            'transaction_date' => '2026-06-01',
        ]);

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('residence.home'))
            ->assertOk()
            ->assertSee('120,00 TL')
            ->assertSee('30,00 TL')
            ->assertSee('Geciken Aidat')
            ->assertSee('Son Ödeme')
            ->assertSee('Gecikti')
            ->assertSee('Gelecek Aidat')
            ->assertSee('Bekliyor')
            ->assertSee('Son Hareketler')
            ->assertSee('Ekstre Hareketi')
            ->assertSee('type="button" disabled', false)
            ->assertSee('Kartla öde')
            ->assertSee('Cari ekstre')
            ->assertDontSee('Tahsil Et')
            ->assertDontSee('Kasaya işle');

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('reports.account-statement', [
                'account_id' => $account->id,
                'date_from' => now()->startOfYear()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Ekstre Hareketi')
            ->assertSee('Geri')
            ->assertSee(route('residence.home'), false)
            ->assertDontSee('Baska Sakin');

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('reports.account-statement', ['account_id' => $other->id]))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession([CurrentApartment::SESSION_KEY => $apartment->id])
            ->get(route('reports.index'))
            ->assertRedirect(route('residence.home'));
    }

    private function managed(User $manager, string $name): Apartment
    {
        $apartment = Apartment::factory()->forUser($manager)->create([
            'name' => $name,
            'is_active' => true,
            'setup_completed_at' => now(),
        ]);
        $apartment->members()->attach($manager->id, ['role' => 'owner', 'is_active' => true]);

        return $apartment;
    }

    /**
     * @return array{0: Apartment, 1: User}
     */
    private function residence(string $building, string $accountName): array
    {
        $manager = User::factory()->create();
        $apartment = $this->managed($manager, $building);
        $member = User::factory()->create(['role' => 'user']);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $this->linkAccount($apartment, $member, '5', Account::TYPE_TENANT, $accountName);

        return [$apartment, $member];
    }

    private function residenceFor(User $member, string $building, string $unitNo): Apartment
    {
        $manager = User::factory()->create();
        $apartment = $this->managed($manager, $building);
        $apartment->members()->attach($member->id, ['role' => 'member', 'is_active' => true]);
        $this->linkAccount($apartment, $member, $unitNo, Account::TYPE_OWNER, $building.' Maliki');

        return $apartment;
    }

    private function linkAccount(Apartment $apartment, User $user, string $unitNo, string $type, string $name): void
    {
        $unit = Unit::create([
            'apartment_id' => $apartment->id,
            'unit_no' => $unitNo,
        ]);

        Account::create([
            'apartment_id' => $apartment->id,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'type' => $type,
            'name' => $name,
            'balance' => 10,
        ]);
    }
}
