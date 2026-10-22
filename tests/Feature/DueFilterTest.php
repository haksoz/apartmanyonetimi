<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, AssessmentPlan, Due, DueBatch, DuePlan, User};
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_debt_types_and_named_plans_filter_independently(): void
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Test Apartmanı', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Malik']);
        $aidat = DuePlan::create([
            'apartment_id' => $apartment->id, 'name' => 'Aidat Kararı', 'plan_year' => 2026,
            'due_type' => 'aidat', 'amount_type' => 'monthly', 'monthly_amount' => 1000,
            'distribution_type' => 'equal', 'target_audience' => 'owner_only', 'due_day' => 1, 'generate_day' => 1,
        ]);
        $assessment = AssessmentPlan::create([
            'apartment_id' => $apartment->id, 'name' => 'Çatı Yenileme', 'total_amount' => 1000,
            'due_type' => 'malik_payi', 'distribution_type' => 'equal', 'target_audience' => 'owner_only',
            'installment_count' => 1, 'generation_mode' => 'on_schedule', 'generate_day' => 1,
        ]);
        $batchData = ['apartment_id' => $apartment->id, 'source_type' => 'manual', 'distribution_type' => 'equal', 'target_audience' => 'owner_only', 'period' => '2026-06', 'source_amount' => 1000];
        $aidatBatch = DueBatch::create([...$batchData, 'due_type' => 'aidat', 'due_plan_id' => $aidat->id]);
        $assessmentBatch = DueBatch::create([...$batchData, 'due_type' => 'malik_payi', 'assessment_plan_id' => $assessment->id]);
        $batch = DueBatch::create([...$batchData, 'due_type' => 'aidat']);
        $dueData = ['apartment_id' => $apartment->id, 'account_id' => $account->id, 'period' => '2026-06', 'amount' => 1000, 'remaining_amount' => 1000, 'due_date' => '2026-06-05', 'status' => 'unpaid'];
        $planDue = Due::create([...$dueData, 'due_type' => 'aidat', 'due_batch_id' => $aidatBatch->id]);
        $assessmentDue = Due::create([...$dueData, 'due_type' => 'malik_payi', 'due_batch_id' => $assessmentBatch->id]);
        $batchDue = Due::create([...$dueData, 'due_type' => 'aidat', 'due_batch_id' => $batch->id]);
        $manualDue = Due::create([...$dueData, 'due_type' => 'masraf_yansitma']);
        $this->withSession([CurrentApartment::SESSION_KEY => $apartment->id])->actingAs($user);
        foreach ([
            [['source' => 'plan'], [$planDue->id]],
            [['source' => 'assessment'], [$assessmentDue->id]],
            [['source' => 'batch'], [$batchDue->id]],
            [['source' => 'manual'], [$manualDue->id]],
            [['due_type' => 'aidat'], [$planDue->id, $batchDue->id]],
            [['due_type' => 'masraf_yansitma'], [$manualDue->id]],
            [['source' => 'assessment', 'plan' => 'assessment:'.$assessment->id, 'due_type' => 'malik_payi', 'period' => '2026-06'], [$assessmentDue->id]],
            [['plan' => 'aidat:'.$aidat->id, 'source' => 'batch'], []],
        ] as [$query, $ids]) {
            $response = $this->get(route('dues.index', $query))->assertOk();
            $this->assertEqualsCanonicalizing($ids, $response->viewData('dues')->pluck('id')->all());
        }
        $this->get(route('dues.index', ['source' => 'assessment', 'plan' => 'assessment:'.$assessment->id]))
            ->assertSee('Çatı Yenileme')->assertSee('Taksitli Borç Planı');
        $this->get(route('dues.index', ['batch_id' => $batch->id]))->assertOk()
            ->assertViewHas('dues', fn ($dues) => $dues->total() === 1);
        $other = Apartment::create(['user_id' => $user->id, 'name' => 'Diğer Apartman', 'unit_count' => 1]);
        $otherPlan = DuePlan::create([...$aidat->fresh()->only($aidat->getFillable()), 'apartment_id' => $other->id, 'name' => 'Başka Apartmanın Planı']);
        $response = $this->get(route('dues.index', ['plan' => 'aidat:'.$otherPlan->id]))->assertOk();
        $this->assertSame(0, $response->viewData('dues')->total());
        $response->assertDontSee('Başka Apartmanın Planı');
    }
}
