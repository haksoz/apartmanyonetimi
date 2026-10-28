<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, Category, Due, Payment, TenantAssignment, Unit, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\TestWith;

class DueTargetAudienceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Rule test apartment', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($apartment);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '1']);
        $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'owner', 'name' => 'Owner', 'is_active' => true]);
        $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Rule test dues',
            'type' => Category::TYPE_INCOME, 'is_active' => true, 'allow_aidat' => true]);
        $apartment->update(['default_aidat_category_id' => $category->id]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);

        return [$apartment, $unit, $owner, $category];
    }

    public function test_batch_rules_include_tenant_priority_owner_fallback_and_owner_only(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $ownerCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Malik İşleri',
            'is_active' => true, 'allow_malik_payi' => true]);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $tenant->id, 'move_in_date' => '2026-06-01']);
        foreach ([['2026-06', 'aidat', 'tenant_priority', $tenant->id], ['2026-07', 'malik_payi', 'owner_only', $owner->id],
            ['2026-02', 'aidat', 'tenant_priority', $owner->id]] as [$period, $type, $rule, $accountId]) {
            $this->post(route('dues.store'), [
                'source_type' => 'fixed', 'distribution_type' => 'equal', 'fixed_amount' => 100,
                'target_audience' => 'invalid', 'due_type' => $type,
                'category_id' => $type === 'aidat' ? $category->id : $ownerCategory->id,
                'period' => $period, 'due_date' => $period.'-10',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $due = Due::where('period', $period)->sole();
            $this->assertSame($rule, $due->target_audience);
            $this->assertSame($accountId, $due->account_id);
        }
    }

    #[TestWith(['aidat', 'tenant_priority'])]
    #[TestWith(['malik_payi', 'owner_only'])]
    public function test_individual_rule_is_derived_from_type_and_preserved_after_editing(string $type, string $rule): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        if ($type === 'malik_payi') {
            $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Malik İşleri',
                'is_active' => true, 'allow_malik_payi' => true]);
        }
        $this->post(route('dues.store'), [
            'source_type' => 'individual', 'distribution_type' => 'individual', 'individual_amount' => 100,
            'target_audience' => $rule === 'owner_only' ? 'tenant_priority' : 'owner_only', 'account_id' => $owner->id,
            'due_type' => $type, 'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $due = Due::sole();
        $this->assertSame($rule, $due->target_audience);
        $this->assertSame($rule, $due->batch->target_audience);
        foreach (['tenant_priority', 'owner_only'] as $reportRule) {
            $report = $this->get(route('reports.monthly-board', [
                'month' => '2026-02', 'target_audience' => $reportRule, 'type_filter' => 'all',
            ]))->assertOk();
            $this->assertEquals($reportRule === $rule ? 100 : 0,
                collect($report->viewData('accountData'))->sum('selectedAmount'));
        }
        $this->editDue($due, $owner, $category);
        $this->assertSame($rule, $due->fresh()->target_audience);
    }

    public function test_individual_create_uses_debt_type_cards_and_preserves_selected_account(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->get(route('dues.create'))->assertOk()
            ->assertSee('Birebir Borçlandırma')->assertSee('Borç Türü')
            ->assertDontSee('Borçlandırma Kapsamı')
            ->assertDontSee('type="radio" name="target_audience"', false)
            ->assertSee('Toplu dağıtım veya otomatik kiracı/kat maliki değişikliği yapılmaz.')
            ->assertViewHas('selectedDueType', 'aidat');
        foreach (['aidat', 'malik_payi'] as $type) {
            $this->get(route('dues.create', ['account_id' => $owner->id, 'due_type' => $type]))
                ->assertOk()->assertSee('Birebir Borçlandırma')
                ->assertViewHas('selectedAccountId', (string) $owner->id)
                ->assertViewHas('selectedDueType', $type)
                ->assertSee('name="account_id" value="'.$owner->id.'"', false);
        }
    }

    public function test_cards_restore_type_and_disable_malik_payi_for_locked_tenant(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        foreach (['aidat', 'malik_payi'] as $type) {
            $html = $this->get(route('dues.create', ['due_type' => $type]))->assertOk()->getContent();
            $xpath = $this->xpath($html);
            $radios = $xpath->query('//input[@type="radio" and @name="due_type"]');
            $this->assertSame(2, $radios->length);
            foreach ($radios as $radio) {
                $this->assertTrue($radio->hasAttribute('required'));
                $this->assertSame($type === $radio->getAttribute('value'), $radio->hasAttribute('checked'));
            }
            $this->assertSame(0, $xpath->query('//select[@name="due_type"]')->length);
        }
        $html = $this->withSession(['_old_input' => ['due_type' => 'malik_payi']])
            ->get(route('dues.create'))->assertOk()->getContent();
        $this->assertTrue($this->xpath($html)->query('//input[@name="due_type" and @value="malik_payi"]')->item(0)->hasAttribute('checked'));
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $html = $this->withSession(['_old_input' => []])->get(route('dues.create', ['account_id' => $tenant->id]))
            ->assertOk()->assertSee('Kiracı hesabına Kat Maliki Payı yazılamaz.')->getContent();
        $xpath = $this->xpath($html);
        $this->assertTrue($xpath->query('//input[@name="due_type" and @value="malik_payi"]')->item(0)->hasAttribute('disabled'));
        $this->assertTrue($xpath->query('//input[@name="due_type" and @value="aidat"]')->item(0)->hasAttribute('checked'));
        $this->assertSame((string) $tenant->id, $xpath->query('//input[@id="locked-account-id"]')->item(0)->getAttribute('value'));
    }

    public function test_both_entry_buttons_link_directly_to_individual_create(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->get(route('dues.index'))->assertOk()
            ->assertSee('href="'.route('dues.create').'"', false)
            ->assertDontSee('target_audience=tenant_priority', false)
            ->assertDontSee('target_audience=owner_only', false);
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        foreach ([$owner, $tenant] as $account) {
            $html = view('accounts._tabs', ['account' => $account, 'active' => 'overview'])->render();
            $url = e(route('dues.create', ['account_id' => $account->id]));
            $this->assertSame(2, substr_count($html, $url)); // Desktop and mobile.
            $this->assertStringNotContainsString('target_audience=', $html);
        }
    }

    public function test_individual_rule_does_not_depend_on_missing_or_forged_scope(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        foreach ([null, '', 'invalid', 'owner_only'] as $index => $scope) {
            $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
                'account_id' => $owner->id, 'individual_amount' => 100, 'due_type' => 'aidat',
                'category_id' => $category->id, 'period' => '2026-0'.($index + 1), 'due_date' => '2026-02-10'];
            if ($scope !== null) $payload['target_audience'] = $scope;
            $this->post(route('dues.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('tenant_priority', Due::where('period', $payload['period'])->sole()->target_audience);
        }
    }

    public function test_malik_payi_rejects_tenant_without_switching_accounts(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $malikCategory = Category::create(['apartment_id' => $apartment->id, 'name' => 'Malik İşleri',
            'is_active' => true, 'allow_malik_payi' => true]);
        $this->getJson(route('dues.create', ['account_id' => $tenant->id, 'due_type' => 'malik_payi']))
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
            'target_audience' => 'tenant_priority', 'account_id' => $tenant->id, 'individual_amount' => 100,
            'due_type' => 'malik_payi', 'category_id' => $malikCategory->id, 'period' => '2026-02', 'due_date' => '2026-02-10'];
        $this->postJson(route('dues.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('account_id');
        foreach (['dues', 'due_batches', 'account_transactions'] as $table) $this->assertDatabaseCount($table, 0);
        $tenant->tenantAssignments()->create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'move_in_date' => '2026-01-01']);
        $payload['due_type'] = 'aidat';
        $payload['category_id'] = $category->id;
        $payload['target_audience'] = 'owner_only';
        $this->post(route('dues.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($tenant->id, Due::sole()->account_id);
        $this->assertSame('tenant_priority', Due::sole()->target_audience);
    }

    public function test_individual_aidat_blocks_explicit_owner_when_the_unit_has_a_tenant(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $unit->update(['occupant_account_id' => $tenant->id]);
        TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $tenant->id, 'move_in_date' => '2026-01-01']);
        $this->post(route('dues.store'), ['source_type' => 'individual', 'distribution_type' => 'individual',
            'account_id' => $owner->id, 'individual_amount' => 100, 'due_type' => 'aidat',
            'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10'])
            ->assertSessionHasErrors('account_id');
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('due_batches', 0);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        return new \DOMXPath($document);
    }

    private function editDue(Due $due, Account $owner, Category $category): void
    {
        $this->patch(route('dues.update', $due), [
            'account_id' => $owner->id, 'category_id' => $category->id, 'due_type' => $due->due_type?->value ?? 'aidat',
            'period' => $due->period, 'due_date' => '2026-02-10', 'amount' => 120,
            'target_audience' => 'owner_only',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(120, $due->fresh()->amount);
    }

    public function test_rule_survives_edit_transfer_and_partial_and_full_payment(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $due = Due::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $owner->id, 'category_id' => $category->id, 'due_type' => 'aidat',
            'period' => '2026-02', 'due_date' => '2026-02-10', 'amount' => 100,
            'remaining_amount' => 100, 'status' => 'unpaid', 'target_audience' => 'tenant_priority']);
        $this->editDue($due, $owner, $category);
        $this->assertSame('tenant_priority', $due->fresh()->target_audience);
        $tenant->tenantAssignments()->create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'move_in_date' => '2026-01-01']);
        $this->post(route('dues.transfer', $due), ['target_account_id' => $tenant->id,
            'target_audience' => 'owner_only'])->assertSessionHasNoErrors()->assertRedirect();
        $due->refresh();
        $this->assertSame($tenant->id, $due->account_id);
        $this->assertSame('tenant_priority', $due->target_audience);
        foreach ([20, 100] as $amount) {
            $payment = Payment::create(['apartment_id' => $apartment->id, 'account_id' => $tenant->id,
                'amount' => $amount, 'unallocated_amount' => $amount, 'payment_date' => '2026-03-01']);
            $payment->allocateToDue($due, $amount);
            $this->assertSame('tenant_priority', $due->fresh()->target_audience);
        }
        $this->assertEquals(0, $due->fresh()->remaining_amount);
        $this->assertEquals(120, $due->allocations()->sum('amount'));
    }

    public function test_migration_preserves_existing_rows_without_assigning_a_rule(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $migration = require database_path('migrations/2026_10_27_000001_add_target_audience_to_dues.php');
        $migration->down();
        $id = \Illuminate\Support\Facades\DB::table('dues')->insertGetId([
            'apartment_id' => $apartment->id, 'account_id' => $owner->id, 'unit_id' => $unit->id,
            'amount' => 100, 'remaining_amount' => 75, 'due_date' => '2026-02-10', 'status' => 'partial',
        ]);
        $migration->up();
        $due = Due::findOrFail($id);
        $this->assertNull($due->target_audience);
        $this->assertEquals(75, $due->remaining_amount);
        $this->assertSame($owner->id, $due->account_id);
    }

    public function test_invalid_rule_is_rejected_when_creating_a_due(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->expectException(\InvalidArgumentException::class);
        Due::create(['apartment_id' => $apartment->id, 'account_id' => $owner->id,
            'unit_id' => $unit->id, 'amount' => 100, 'remaining_amount' => 100,
            'due_date' => '2026-02-10', 'status' => 'unpaid', 'target_audience' => 'invalid']);
    }

    public function test_existing_rule_cannot_be_overwritten_on_the_model(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $due = Due::create(['apartment_id' => $apartment->id, 'account_id' => $owner->id,
            'unit_id' => $unit->id, 'amount' => 100, 'remaining_amount' => 100,
            'due_date' => '2026-02-10', 'status' => 'unpaid']);
        $this->assertSame('tenant_priority', $due->target_audience);
        $this->expectException(\LogicException::class);
        $due->update(['target_audience' => 'owner_only']);
    }
}
