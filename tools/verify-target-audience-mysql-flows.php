<?php

// Included only by the restricted MySQL clone runner inside a rollback transaction.
use App\Enums\DueType;
use App\Http\Controllers\{DueController, DuePlanController, PaymentController, ReportController};
use App\Models\{Account, Apartment, AssessmentPlan, CashBox, CashTransaction, Category, Due, DuePlan, Payment, TenantAssignment, Unit, User};
use App\Support\{AssessmentPlanGenerator, CurrentApartment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;

ensure(DB::transactionLevel() > 0 && str_starts_with(DB::connection()->getDatabaseName(), 'aidatcepstage2test'), 'Flow runner requires an isolated clone transaction.');
$results = [];
$check = function (string $name, bool $passed) use (&$results, &$report, $saveReport): void {
    ensure($passed, 'Application flow failed: '.$name);
    $results[$name] = true;
    $report['application_flow_progress'][$name] = true;
    $saveReport();
};
$requestFor = function (string $path, array $payload, string $method = 'POST'): Request {
    $request = Request::create($path, $method, $payload);
    $request->setLaravelSession(app('session')->driver());
    $request->setUserResolver(fn () => Auth::user());
    app()->instance('request', $request);
    return $request;
};
$user = User::factory()->create(['name' => 'Isolated MySQL verification', 'email' => 'mysql-stage2-'.bin2hex(random_bytes(5)).'@example.invalid']);
$apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Temporary MySQL flow fixture', 'unit_count' => 2]);
$apartment->members()->attach($user->id, ['role' => 'owner']);
Category::createDefaultsFor($apartment->id);
$apartment->refresh();
$category = Category::where('apartment_id', $apartment->id)->where('name', 'Ceza')->firstOrFail();
$interestCategory = Category::where('apartment_id', $apartment->id)->where('name', 'Faiz')->firstOrFail();
$otherCategory = Category::where('apartment_id', $apartment->id)->where('name', 'Diğer')->firstOrFail();
$owners = [];
$units = [];
foreach ([1, 2] as $number) {
    $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => (string) $number, 'square_meters' => 50, 'share_coefficient' => 1]);
    $owner = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $unit->id, 'type' => 'owner', 'name' => 'Fixture owner '.$number, 'is_active' => true]);
    $unit->update(['owner_account_id' => $owner->id, 'occupant_account_id' => $owner->id]);
    $owners[] = $owner;
    $units[] = $unit;
}
$tenant = Account::create(['apartment_id' => $apartment->id, 'unit_id' => $units[0]->id, 'type' => 'tenant', 'name' => 'Fixture tenant', 'is_active' => true]);
TenantAssignment::create(['apartment_id' => $apartment->id, 'unit_id' => $units[0]->id, 'account_id' => $tenant->id, 'move_in_date' => '2026-01-01']);
$units[0]->update(['occupant_account_id' => $tenant->id]);
Auth::setUser($user);
session([CurrentApartment::SESSION_KEY => $apartment->id]);
$current = app(CurrentApartment::class);
$controller = app(DueController::class);
$individual = ['source_type' => 'individual', 'distribution_type' => 'individual', 'individual_amount' => 100,
    'due_type' => 'aidat', 'category_id' => $apartment->default_aidat_category_id, 'period' => '2026-06', 'due_date' => '2026-06-10'];
$controller->store($requestFor('/dues', [...$individual, 'account_id' => $tenant->id]), $current);
$aidat = Due::where('apartment_id', $apartment->id)->sole();
$check('individual_aidat_targets_tenant', $aidat->account_id === $tenant->id && $aidat->due_type === DueType::Aidat);
try {
    $controller->store($requestFor('/dues', [...$individual, 'account_id' => $owners[0]->id]), $current);
    ensure(false, 'Owner Aidat bypassed tenant targeting.');
} catch (ValidationException $exception) {
    $check('aidat_rejects_owner_when_tenant_exists', isset($exception->errors()['account_id']));
}
$controller->store($requestFor('/dues', [...$individual, 'due_type' => 'malik_payi', 'category_id' => $category->id, 'account_id' => $owners[0]->id]), $current);
$malik = Due::where('apartment_id', $apartment->id)->where('due_type', 'malik_payi')->sole();
$check('individual_malik_payi_targets_owner', $malik->account_id === $owners[0]->id);
try {
    $controller->store($requestFor('/dues', [...$individual, 'due_type' => 'malik_payi', 'category_id' => $category->id, 'account_id' => $tenant->id]), $current);
    ensure(false, 'Tenant received owner debt.');
} catch (ValidationException $exception) {
    $check('malik_payi_rejects_tenant', isset($exception->errors()['account_id']));
}
$controller->store($requestFor('/dues', ['source_type' => 'fixed', 'distribution_type' => 'equal', 'fixed_amount' => 100,
    'due_type' => 'aidat', 'category_id' => $apartment->default_aidat_category_id, 'period' => '2026-07', 'due_date' => '2026-07-10']), $current);
$batchDues = Due::where('apartment_id', $apartment->id)->where('period', '2026-07')->get();
$check('batch_aidat_tenant_and_owner_fallback', $batchDues->count() === 2
    && $batchDues->pluck('account_id')->sort()->values()->all() === collect([$tenant->id, $owners[1]->id])->sort()->values()->all());
$controller->store($requestFor('/dues', ['source_type' => 'manual', 'distribution_type' => 'equal', 'source_amount' => 120,
    'due_type' => 'malik_payi', 'category_id' => $otherCategory->id, 'period' => '2026-07', 'due_date' => '2026-07-10']), $current);
$check('batch_malik_payi_owners_only', Due::where('apartment_id', $apartment->id)->where('period', '2026-07')->where('due_type', 'malik_payi')
    ->pluck('account_id')->sort()->values()->all() === collect($owners)->pluck('id')->sort()->values()->all());
try {
    $aidat->update(['due_type' => 'malik_payi']);
    ensure(false, 'Saved due type changed.');
} catch (ValidationException $exception) {
    $check('saved_due_type_immutable', isset($exception->errors()['due_type']) && $aidat->fresh()->due_type === DueType::Aidat);
}
$aidat->refresh();
$box = CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Fixture cash', 'is_active' => true]);
app(PaymentController::class)->store($requestFor('/payments', ['account_id' => $tenant->id, 'amount' => 40,
    'payment_date' => '2026-06-20', 'cash_box_id' => $box->id, 'action' => 'fifo_popup',
    'allocations' => [['due_id' => $aidat->id, 'amount' => 40]]]), $current);
$payment = Payment::where('apartment_id', $apartment->id)->sole();
$check('payment_allocation_and_remaining_balance', (float) $aidat->fresh()->remaining_amount === 60.0
    && (float) $payment->fresh()->unallocated_amount === 0.0 && (float) $payment->allocations()->sum('amount') === 40.0);
$check('payment_ledger_and_cash_relationships', (float) $payment->transactions()->sum('amount') === 40.0
    && CashTransaction::where('payment_id', $payment->id)->where('cash_box_id', $box->id)->count() === 1);

$plan = DuePlan::create(['apartment_id' => $apartment->id, 'name' => 'Fixture Aidat Plan', 'plan_year' => 2026,
    'category_id' => $apartment->default_aidat_category_id, 'amount_type' => 'monthly', 'monthly_amount' => 200,
    'distribution_type' => 'equal', 'due_day' => 10, 'generate_day' => 1, 'is_active' => true]);
$planController = app(DuePlanController::class);
$check('automatic_aidat_plan_generation', $planController->createDuesForPeriod($plan, '2026-08') === 2);
$check('automatic_aidat_plan_targeting', Due::where('apartment_id', $apartment->id)->where('period', '2026-08')->where('due_type', 'aidat')
    ->pluck('account_id')->sort()->values()->all() === collect([$tenant->id, $owners[1]->id])->sort()->values()->all());
$check('automatic_aidat_plan_idempotence', $planController->createDuesForPeriod($plan, '2026-08') === 0);

$assessment = AssessmentPlan::create(['apartment_id' => $apartment->id, 'name' => 'Fixture Installment Plan',
    'total_amount' => 900, 'distribution_type' => 'equal', 'category_id' => $interestCategory->id,
    'installment_count' => 3, 'generation_mode' => 'create_all_now', 'generate_day' => 1, 'is_active' => true]);
$generator = app(AssessmentPlanGenerator::class);
$generator->buildDistribution($assessment, [['due_date' => '2026-08-10'], ['due_date' => '2026-09-10'], ['due_date' => '2026-10-10']]);
$generated = 0;
foreach ($assessment->installments()->orderBy('sequence')->get() as $installment) {
    $generated += $generator->generateInstallment($assessment, $installment);
}
$assessmentDues = Due::whereHas('batch', fn ($query) => $query->where('assessment_plan_id', $assessment->id))->get();
$check('installment_plan_generation_and_amount', $generated === 6 && (float) $assessmentDues->sum('amount') === 900.0);
$check('installment_plan_owners_only', $assessmentDues->every(fn ($due) => $due->due_type === DueType::MalikPayi && in_array($due->account_id, [$owners[0]->id, $owners[1]->id], true)));
$check('installment_plan_idempotence', $generator->generateInstallment($assessment, $assessment->installments()->firstOrFail()) === 0);

view()->share('errors', new Illuminate\Support\ViewErrorBag);
$reportController = app(ReportController::class);
foreach ([['2026-06', 'aidat', 100], ['2026-06', 'malik_payi', 100], ['2026-07', 'malik_payi', 120], ['2026-08', 'malik_payi', 300]] as [$month, $type, $expected]) {
    $request = $requestFor('/reports/monthly-board', ['month' => $month, 'due_type' => $type, 'view_mode' => 'current'], 'GET');
    $view = $reportController->monthlyBoard($current, $request);
    $data = $view->getData();
    $check('monthly_report_'.$month.'_'.$type, (float) collect($data['accountData'])->sum('selectedAmount') === (float) $expected);
    $html = $view->render();
    $check('six_columns_'.$month.'_'.$type, substr_count($html, '<th class="px-4 py-3') === 6);
    $pdfHtml = view('reports.monthly-board-pdf', $data)->render();
    $check('pdf_template_'.$month.'_'.$type, substr_count($pdfHtml, '<th>') + substr_count($pdfHtml, '<th class=') === 6);
    $excel = $reportController->monthlyBoardExport($current, $request, 'excel');
    ob_start();
    try {
        ($excel->getCallback())();
        $bytes = ob_get_contents();
    } finally {
        ob_end_clean();
    }
    $check('excel_export_'.$month.'_'.$type, str_starts_with($bytes, 'PK') && strlen($bytes) > 1000);
}
$results['fixtures_rolled_back_by_parent'] = true;
return $results;
