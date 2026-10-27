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
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'account_id' => $tenant->id, 'move_in_date' => '2026-06-01']);
        foreach ([['2026-06', 'tenant_priority', $tenant->id], ['2026-07', 'owner_only', $owner->id],
            ['2026-02', 'tenant_priority', $owner->id]] as [$period, $rule, $accountId]) {
            $this->post(route('dues.store'), [
                'source_type' => 'fixed', 'distribution_type' => 'equal', 'fixed_amount' => 100,
                'target_audience' => $rule, 'due_type' => 'aidat', 'category_id' => $category->id,
                'period' => $period, 'due_date' => $period.'-10',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $due = Due::where('period', $period)->sole();
            $this->assertSame($rule, $due->target_audience);
            $this->assertSame($accountId, $due->account_id);
            $this->get(route('dues.show', $due))->assertOk()->assertSee('Oluşturma Kuralı')
                ->assertSee($rule === 'owner_only' ? 'Sadece Kat Maliki' : 'Kiracı Öncelikli');
        }
    }

    #[TestWith(['tenant_priority'])]
    #[TestWith(['owner_only'])]
    public function test_individual_explicit_rule_is_recorded_and_preserved_after_editing(string $rule): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $this->post(route('dues.store'), [
            'source_type' => 'individual', 'distribution_type' => 'individual', 'individual_amount' => 100,
            'target_audience' => $rule, 'account_id' => $owner->id,
            'due_type' => 'aidat', 'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10',
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
        $this->get(route('dues.show', $due))->assertOk()->assertSee($rule === 'owner_only' ? 'Sadece Kat Maliki' : 'Kiracı Öncelikli');
    }

    public function test_individual_create_requires_a_visible_choice_and_preserves_the_selected_account(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->get(route('dues.create'))->assertOk()
            ->assertSee('Borçlandırma Kapsamı')->assertSee('Kapsam seçin')
            ->assertDontSee('type="hidden" name="target_audience"', false)
            ->assertViewHas('targetAudience', null);
        foreach (['tenant_priority' => 'Daire Sakini Borçlandırması', 'owner_only' => 'Kat Maliki Borçlandırması'] as $rule => $title) {
            $this->get(route('dues.create', ['account_id' => $owner->id, 'target_audience' => $rule]))
                ->assertOk()->assertSee($title)->assertViewHas('selectedAccountId', (string) $owner->id)
                ->assertViewHas('targetAudience', $rule)
                ->assertSee('name="account_id" value="'.$owner->id.'"', false);
        }
    }

    public function test_individual_scope_cards_restore_selection_and_disable_owner_card_for_locked_tenant(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        foreach ([null, 'tenant_priority', 'owner_only'] as $scope) {
            $parameters = $scope ? ['target_audience' => $scope] : [];
            $html = $this->get(route('dues.create', $parameters))->assertOk()->getContent();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xpath = new \DOMXPath($document);
            $radios = $xpath->query('//input[@type="radio" and @name="target_audience"]');
            $this->assertSame(2, $radios->length);
            foreach ($radios as $radio) {
                $this->assertTrue($radio->hasAttribute('required'));
                $this->assertSame($scope === $radio->getAttribute('value'), $radio->hasAttribute('checked'));
            }
            $this->assertSame(0, $xpath->query('//select[@name="target_audience"]')->length);
        }
        $html = $this->get(route('dues.create', ['account_id' => $tenant->id, 'target_audience' => 'tenant_priority']))
            ->assertOk()->getContent();
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertTrue($xpath->query('//input[@name="target_audience" and @value="owner_only"]')->item(0)->hasAttribute('disabled'));
    }

    public function test_both_entry_menus_offer_explicit_scopes_and_tenant_menu_disables_owner_scope(): void
    {
        [$apartment, $unit, $owner] = $this->fixture();
        $this->get(route('dues.index'))->assertOk()
            ->assertSee(route('dues.create', ['target_audience' => 'tenant_priority']), false)
            ->assertSee(route('dues.create', ['target_audience' => 'owner_only']), false);
        $html = view('accounts._tabs', ['account' => $owner, 'active' => 'overview'])->render();
        foreach (['tenant_priority', 'owner_only'] as $rule) {
            $url = e(route('dues.create', ['account_id' => $owner->id, 'target_audience' => $rule]));
            $this->assertSame(2, substr_count($html, $url)); // Desktop and mobile.
        }
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $html = view('accounts._tabs', ['account' => $tenant, 'active' => 'overview'])->render();
        $this->assertStringNotContainsString('target_audience=owner_only', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_missing_or_invalid_individual_scope_cannot_create_any_financial_record(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        foreach ([null, '', 'invalid'] as $scope) {
            $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
                'account_id' => $owner->id, 'individual_amount' => 100, 'due_type' => 'aidat',
                'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10'];
            if ($scope !== null) $payload['target_audience'] = $scope;
            $this->postJson(route('dues.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('target_audience');
        }
        foreach (['dues', 'due_batches', 'account_transactions', 'payments', 'payment_allocations', 'cash_transactions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_owner_scope_rejects_tenant_account_on_get_and_post_without_switching_accounts(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id,
            'type' => 'tenant', 'name' => 'Tenant', 'is_active' => true]);
        $this->getJson(route('dues.create', ['account_id' => $tenant->id, 'target_audience' => 'owner_only']))
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
            'target_audience' => 'owner_only', 'account_id' => $tenant->id, 'individual_amount' => 100,
            'due_type' => 'aidat', 'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10'];
        $this->postJson(route('dues.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertDatabaseCount('dues', 0);
        $this->assertDatabaseCount('due_batches', 0);
        $payload['target_audience'] = 'tenant_priority';
        $this->post(route('dues.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $due = Due::sole();
        $this->assertSame($tenant->id, $due->account_id);
        $this->assertSame('tenant_priority', $due->target_audience);
    }

    public function test_resident_scope_blocks_malik_payi_in_the_form_and_direct_submission(): void
    {
        [$apartment, $unit, $owner, $category] = $this->fixture();
        $category = Category::create(['apartment_id' => $apartment->id, 'name' => 'Owner repair',
            'type' => Category::TYPE_INCOME, 'is_active' => true, 'allow_malik_payi' => true]);
        foreach (['tenant_priority' => true, 'owner_only' => false] as $scope => $blocked) {
            $html = $this->get(route('dues.create', ['target_audience' => $scope]))->assertOk()->getContent();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xpath = new \DOMXPath($document);
            $option = $xpath->query('//select[@id="due_type"]/option[@value="malik_payi"]')->item(0);
            $this->assertNotNull($option);
            $this->assertSame($blocked, $option->hasAttribute('disabled'));
            $this->assertSame($blocked, $option->hasAttribute('hidden'));
        }
        $payload = ['source_type' => 'individual', 'distribution_type' => 'individual',
            'target_audience' => 'tenant_priority', 'account_id' => $owner->id, 'individual_amount' => 100,
            'due_type' => 'malik_payi', 'category_id' => $category->id, 'period' => '2026-02', 'due_date' => '2026-02-10'];
        $this->postJson(route('dues.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('due_type');
        foreach (['dues', 'due_batches', 'account_transactions'] as $table) $this->assertDatabaseCount($table, 0);
        $payload['target_audience'] = 'owner_only';
        $this->post(route('dues.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $due = Due::sole();
        $this->assertSame('malik_payi', $due->due_type->value);
        $this->assertSame('owner_only', $due->target_audience);
    }

    private function editDue(Due $due, Account $owner, Category $category): void
    {
        $this->patch(route('dues.update', $due), [
            'account_id' => $owner->id, 'category_id' => $category->id, 'due_type' => 'aidat',
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
        $this->assertNull($due->target_audience);
        $this->expectException(\LogicException::class);
        $due->update(['target_audience' => 'owner_only']);
    }
}
