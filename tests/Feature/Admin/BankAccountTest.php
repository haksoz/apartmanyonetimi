<?php

namespace Tests\Feature\Admin;

use App\Models\BankAccount;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_bank_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.bank-accounts.store'), [
                'name' => 'İş Bankası TL',
                'bank_name' => 'Türkiye İş Bankası',
                'branch' => 'Kartal',
                'account_holder' => 'AidatCep Yazılım A.Ş.',
                'account_number' => '1234567',
                'iban' => 'TR000123456789012345678901',
                'currency' => 'TRY',
                'is_active' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.bank-accounts.index'));

        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'İş Bankası TL',
            'iban' => 'TR000123456789012345678901',
            'is_active' => true,
        ]);
    }

    public function test_non_admin_cannot_access_bank_accounts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.bank-accounts.index'))
            ->assertForbidden();
    }

    public function test_admin_can_update_bank_account(): void
    {
        $admin = User::factory()->admin()->create();
        $account = BankAccount::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.bank-accounts.update', $account), [
                'name' => 'Garanti BBVA TL',
                'bank_name' => 'Garanti BBVA',
                'account_holder' => 'AidatCep Yazılım A.Ş.',
                'iban' => 'TR009876543210987654321098',
                'currency' => 'TRY',
                'is_active' => '0',
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.bank-accounts.index'));

        $account->refresh();
        $this->assertSame('Garanti BBVA TL', $account->name);
        $this->assertFalse($account->is_active);
    }

    public function test_inactive_bank_account_is_not_shown_to_subscribers(): void
    {
        $active = BankAccount::factory()->create(['is_active' => true, 'name' => 'Aktif Banka Hesabı']);
        BankAccount::factory()->create(['is_active' => false, 'name' => 'Pasif Banka Hesabı']);

        $package = Package::factory()->create(['monthly_price' => 100]);
        $manager = User::factory()->withSubscription($package)->create();

        // Create a pending subscription to reach receipt page
        $subscription = \App\Models\UserSubscription::factory()->create([
            'user_id' => $manager->id,
            'package_id' => $package->id,
            'status' => \App\Models\UserSubscription::STATUS_PENDING,
            'is_active' => false,
        ]);

        $this->actingAs($manager)
            ->get(route('subscriber.subscriptions.receipt', $subscription))
            ->assertOk()
            ->assertSee($active->name)
            ->assertDontSeeText('Pasif Banka Hesabı');
    }
}
