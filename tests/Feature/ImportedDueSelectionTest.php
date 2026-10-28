<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Category, Due, TenantAssignment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedDueSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $hasTenant = false): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::factory()->forUser($user)->create(['unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'owner', 'name' => 'Malik', 'is_active' => true]);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'tenant', 'name' => 'Kiracı', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $hasTenant ? $tenant->id : $owner->id]);
        if ($hasTenant) {
            TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'account_id' => $tenant->id, 'move_in_date' => '2026-01-01']);
        }
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Ceza', 'is_active' => true, 'allow_aidat' => true, 'allow_malik_payi' => true]);
        $this->actingAs($user)->withSession([
            CurrentApartment::SESSION_KEY => $apartment->id,
            'bulk_import_apartment_id' => $apartment->id,
            'bulk_import_accounts' => ['owner' => ['name' => 'Malik', 'unit_no' => '1', 'unit_id' => $unit->id,
                'existing_account_id' => $owner->id, 'suggested_type' => 'owner', 'date_range' => '10.06.2026', 'transaction_count' => 1],
                'tenant' => ['name' => 'Kiracı', 'unit_no' => '1', 'unit_id' => $unit->id,
                    'existing_account_id' => $tenant->id, 'suggested_type' => 'tenant', 'date_range' => '10.06.2026', 'transaction_count' => 0]],
            'bulk_import_transactions' => [$this->row('owner', 2)],
        ]);
        return [$apartment, $owner, $tenant, $category];
    }

    private function row(string $key, int $row): array
    {
        return ['account_key' => $key, 'account_name' => $key, 'unit_no' => '1', 'date' => '2026-06-10', 'display_date' => '10.06.2026',
            'category_name' => 'Ceza', 'description' => 'İçe aktarılan borç', 'credit' => 0, 'debit' => 100, 'row_number' => $row];
    }

    private function payload(Account $owner, Account $tenant, Category $category, string $type = 'aidat'): array
    {
        return ['account_types' => ['owner' => 'owner', 'tenant' => 'tenant'],
            'account_mapping' => ['owner' => $owner->id, 'tenant' => $tenant->id],
            'due_types' => [2 => $type], 'due_categories' => [2 => (string) $category->id]];
    }

    public function test_preview_exposes_every_debt_row_for_explicit_type_and_category_selection(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture();
        $this->get(route('accounts.bulk-import-preview-page'))->assertOk()
            ->assertSee('name="due_types[2]"', false)->assertSee('name="due_categories[2]"', false)
            ->assertSee('Kat Maliki Payı');
    }

    public function test_missing_type_is_not_inferred_from_category_or_owner_and_writes_nothing(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture();
        $payload = $this->payload($owner, $tenant, $category);
        unset($payload['due_types']);
        $this->postJson(route('accounts.bulk-import-confirm'), $payload)->assertUnprocessable()->assertJsonValidationErrors('due_types.2');
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('cash_boxes', 0);
        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_selected_aidat_type_and_category_are_used_without_name_based_inference(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture();
        // Yalnız malik içe aktarılıyor; listede seçilmeyen kiracı için atama oluşturulmaz.
        $this->withSession(['bulk_import_accounts' => ['owner' => ['name' => 'Malik', 'unit_no' => '1', 'unit_id' => $owner->unit_id]]]);
        $this->post(route('accounts.bulk-import-confirm'), $this->payload($owner, $tenant, $category))->assertSessionHasNoErrors();
        $this->assertSame('aidat', Due::sole()->due_type->value);
        $this->assertSame('tenant_priority', Due::sole()->target_audience);
        $this->assertSame($category->id, Due::sole()->category_id);
    }

    public function test_owner_aidat_with_tenant_is_rejected_and_the_whole_import_rolls_back(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture(true);
        $this->withSession(['bulk_import_transactions' => [$this->row('tenant', 2), $this->row('owner', 3)]]);
        $payload = $this->payload($owner, $tenant, $category);
        $payload['due_types'][3] = 'aidat';
        $payload['due_categories'][3] = (string) $category->id;
        $this->postJson(route('accounts.bulk-import-confirm'), $payload)->assertUnprocessable()->assertJsonValidationErrors('due_types.3');
        foreach (['dues', 'account_transactions', 'cash_boxes', 'payments', 'payment_allocations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('accounts', 2);
    }

    public function test_malik_payi_accepts_owner_with_tenant_but_rejects_tenant(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture(true);
        $this->withSession(['bulk_import_transactions' => [$this->row('tenant', 2)]]);
        $payload = $this->payload($owner, $tenant, $category, 'malik_payi');
        $this->postJson(route('accounts.bulk-import-confirm'), $payload)->assertUnprocessable()->assertJsonValidationErrors('due_types.2');
        $this->withSession(['bulk_import_transactions' => [$this->row('owner', 2)]]);
        $this->post(route('accounts.bulk-import-confirm'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('malik_payi', Due::sole()->due_type->value);
        $this->assertSame('owner_only', Due::sole()->target_audience);
    }

    public function test_category_permission_is_required_and_never_silently_enabled(): void
    {
        [$apartment, $owner, $tenant, $category] = $this->fixture();
        $category->update(['allow_aidat' => false]);
        $this->postJson(route('accounts.bulk-import-confirm'), $this->payload($owner, $tenant, $category))
            ->assertUnprocessable()->assertJsonValidationErrors('due_categories.2');
        $this->assertFalse($category->fresh()->allow_aidat);
        $this->assertDatabaseCount('dues', 0);
    }
}
