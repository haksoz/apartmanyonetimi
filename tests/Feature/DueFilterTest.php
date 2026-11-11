<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\AssessmentPlan;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_recognizes_unit_aliases_and_description_words(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => '08']);
        $data = ['apartment_id' => $apartment->id, 'account_id' => $account->id, 'amount' => 100,
            'remaining_amount' => 100, 'due_date' => '2026-06-05', 'status' => 'unpaid', 'due_type' => 'aidat'];
        $byUnit = Due::create([...$data, 'unit_id' => $unit->id, 'description' => 'Aidat']);
        $byDescription = Due::create([...$data, 'description' => 'Daire 08: ÇATI-onarımı']);
        Due::create([...$data, 'description' => 'Daire 80: Çatı onarımı']);
        Due::create([...$data, 'description' => 'Daire 18: Çatı onarımı']);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);

        foreach (['D8', 'd08', 'Daire 08', 'D 8', 'D:08'] as $search) {
            $response = $this->get(route('dues.index', ['search' => $search, 'from' => 'menu']))->assertOk();
            $this->assertEqualsCanonicalizing([$byUnit->id, $byDescription->id], $response->viewData('dues')->pluck('id')->all());
        }
        foreach (['D8 cati ONARIMI', 'onarimi daire 8', 'D8 çatı'] as $search) {
            $response = $this->get(route('dues.index', ['search' => $search]))->assertOk();
            $this->assertSame([$byDescription->id], $response->viewData('dues')->pluck('id')->all());
        }
        $this->get(route('dues.index', ['search' => '%']))->assertOk()
            ->assertViewHas('dues', fn ($dues) => $dues->total() === 0);
    }

    public function test_sources_debt_types_and_named_plans_filter_independently(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        $aidat = DuePlan::create([
            'apartment_id' => $apartment->id, 'name' => 'Aidat Kararı', 'plan_year' => 2026,
            'due_type' => 'aidat', 'amount_type' => 'monthly', 'monthly_amount' => 1000,
            'distribution_type' => 'equal', 'due_day' => 1, 'generate_day' => 1,
        ]);
        $assessment = AssessmentPlan::create([
            'apartment_id' => $apartment->id, 'name' => 'Çatı Yenileme', 'total_amount' => 1000,
            'due_type' => 'malik_payi', 'distribution_type' => 'equal', 'installment_count' => 1, 'generation_mode' => 'on_schedule', 'generate_day' => 1,
        ]);
        $batchData = ['apartment_id' => $apartment->id, 'source_type' => 'manual', 'distribution_type' => 'equal', 'period' => '2026-06', 'source_amount' => 1000];
        $aidatBatch = DueBatch::create([...$batchData, 'due_type' => 'aidat', 'due_plan_id' => $aidat->id]);
        $assessmentBatch = DueBatch::create([...$batchData, 'due_type' => 'malik_payi', 'assessment_plan_id' => $assessment->id]);
        $batch = DueBatch::create([...$batchData, 'due_type' => 'aidat']);
        $individualBatch = DueBatch::create([...$batchData, 'due_type' => 'malik_payi',
            'source_type' => DueBatch::SOURCE_INDIVIDUAL, 'distribution_type' => DueBatch::DISTRIBUTION_INDIVIDUAL]);
        $dueData = ['apartment_id' => $apartment->id, 'account_id' => $account->id, 'period' => '2026-06', 'amount' => 1000, 'remaining_amount' => 1000, 'due_date' => '2026-06-05', 'status' => 'unpaid'];
        $planDue = Due::create([...$dueData, 'due_type' => 'aidat', 'due_batch_id' => $aidatBatch->id]);
        $assessmentDue = Due::create([...$dueData, 'due_type' => 'malik_payi', 'due_batch_id' => $assessmentBatch->id]);
        $batchDue = Due::create([...$dueData, 'due_type' => 'aidat', 'due_batch_id' => $batch->id]);
        $manualDue = Due::create([...$dueData, 'due_type' => 'malik_payi']);
        $individualDue = Due::create([...$dueData, 'due_type' => 'malik_payi', 'due_batch_id' => $individualBatch->id]);
        $importedDue = Due::create([...$dueData, 'due_type' => 'aidat', 'period' => null, 'created_at_manual' => '2026-01-01',
            'due_date' => '2026-01-05', 'is_imported' => true]);
        $unusedPlan = DuePlan::create([
            'apartment_id' => $apartment->id, 'name' => 'Sonuçsuz Plan', 'plan_year' => 2025,
            'due_type' => 'aidat', 'amount_type' => 'monthly', 'monthly_amount' => 500,
            'distribution_type' => 'equal', 'due_day' => 1, 'generate_day' => 1,
        ]);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        $this->get(route('dues.show', $individualDue))->assertOk()
            ->assertSee('Birebir Borçlandırma')
            ->assertDontSee('>Toplu Borçlandırma</span>', false);
        foreach ([
            [['source' => 'plan'], [$planDue->id]],
            [['source' => 'assessment'], [$assessmentDue->id]],
            [['source' => 'batch'], [$batchDue->id]],
            [['source' => 'manual'], [$manualDue->id, $individualDue->id]],
            [['source' => 'import'], [$importedDue->id]],
            [['source' => 'import', 'period' => '2026-01'], [$importedDue->id]],
            [['status' => 'open'], [$planDue->id, $assessmentDue->id, $batchDue->id, $manualDue->id, $individualDue->id]],
            [['due_type' => 'aidat'], [$planDue->id, $batchDue->id]],
            [['due_type' => 'malik_payi'], [$manualDue->id, $individualDue->id, $assessmentDue->id]],
            [['source' => 'assessment', 'plan' => 'assessment:'.$assessment->id, 'due_type' => 'malik_payi', 'period' => '2026-06'], [$assessmentDue->id]],
            [['plan' => 'aidat:'.$aidat->id, 'source' => 'batch'], []],
        ] as [$query, $ids]) {
            $response = $this->get(route('dues.index', $query))->assertOk();
            $this->assertEqualsCanonicalizing($ids, $response->viewData('dues')->pluck('id')->all());
        }
        $this->get(route('dues.index', ['source' => 'assessment', 'plan' => 'assessment:'.$assessment->id]))
            ->assertSee('Çatı Yenileme')->assertSee('Taksitli Borç Planı');
        $this->get(route('dues.index'))->assertOk()
            ->assertSee('Tüm Açık Borçlar')
            ->assertSee('Vadesi Gelmemiş')
            ->assertSee('İçe Aktarma')
            ->assertDontSee('value="supplier"', false)
            ->assertDontSee($unusedPlan->name);
        $this->get(route('dues.index', ['batch_id' => $batch->id]))->assertOk()
            ->assertViewHas('dues', fn ($dues) => $dues->total() === 1);
        $other = Apartment::create(['user_id' => $user->id, 'name' => 'Diğer Apartman', 'unit_count' => 1]);
        $otherPlan = DuePlan::create([...$aidat->fresh()->only($aidat->getFillable()), 'apartment_id' => $other->id, 'name' => 'Başka Apartmanın Planı']);
        $response = $this->get(route('dues.index', ['plan' => 'aidat:'.$otherPlan->id]))->assertOk();
        $this->assertSame(0, $response->viewData('dues')->total());
        $response->assertDontSee('Başka Apartmanın Planı');
    }
}
