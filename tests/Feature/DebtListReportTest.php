<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\Due;
use App\Models\Unit;
use App\Models\User;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DebtListReportTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $apartment;
    private Account $owner;
    private Account $tenant;
    private Account $otherOwner;
    private Account $formerTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Borç Apartmanı', 'unit_count' => 2]);
        $this->apartment->members()->attach($user->id, ['role' => 'owner']);
        $this->grantPaidFeatures($this->apartment);

        $unitOne = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '1']);
        $unitTwo = Unit::create(['apartment_id' => $this->apartment->id, 'unit_no' => '2']);
        $this->owner = $this->account($unitOne, Account::TYPE_OWNER, 'Aktif Malik');
        $this->tenant = $this->account($unitOne, Account::TYPE_TENANT, 'Güncel Kiracı');
        $this->formerTenant = $this->account($unitOne, Account::TYPE_TENANT, 'Eski Kiracı', false);
        $this->otherOwner = $this->account($unitTwo, Account::TYPE_OWNER, 'İkinci Malik');
        $unitOne->update(['owner_account_id' => $this->owner->id, 'occupant_account_id' => $this->tenant->id]);
        $unitTwo->update(['owner_account_id' => $this->otherOwner->id, 'occupant_account_id' => $this->otherOwner->id]);
        $this->tenant->tenantAssignments()->create([
            'apartment_id' => $this->apartment->id,
            'unit_id' => $unitOne->id,
            'move_in_date' => today()->subMonth(),
        ]);

        $this->due($this->tenant, 'aidat', 1000, 400, 'Kısmi aidat');
        $this->due($this->tenant, 'malik_payi', 50, 50, 'Kiracıdaki tarihsel malik payı');
        $this->due($this->owner, 'aidat', 300, 300, 'Malik aidatı');
        $this->due($this->otherOwner, 'malik_payi', 500, 500, 'Demirbaş payı');
        $this->due($this->formerTenant, 'aidat', 200, 200, 'Eski kiracı borcu');
        $this->due($this->tenant, 'aidat', 75, 0, 'Kapanmış borç');
        $this->formerTenant->delete();

        $this->withSession([CurrentApartment::SESSION_KEY => $this->apartment->id])->actingAs($user);
    }

    private function account(Unit $unit, string $type, string $name, bool $active = true): Account
    {
        return Account::create([
            'apartment_id' => $this->apartment->id,
            'unit_id' => $unit->id,
            'type' => $type,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function due(Account $account, string $type, float $amount, float $remaining, string $description): Due
    {
        return Due::create([
            'apartment_id' => $this->apartment->id,
            'account_id' => $account->id,
            'unit_id' => $account->unit_id,
            'due_type' => $type,
            'period' => '2026-09',
            'due_date' => '2026-09-15',
            'created_at_manual' => '2026-09-01',
            'amount' => $amount,
            'remaining_amount' => $remaining,
            'status' => $remaining > 0 ? 'unpaid' : 'paid',
            'description' => $description,
        ]);
    }

    public function test_debt_type_filter_uses_the_due_type_and_excludes_closed_debts(): void
    {
        $all = $this->get(route('reports.debt-list'))->assertOk();
        $this->assertSame('Genel Borç Listesi', $all->viewData('tableTitle'));
        $this->assertEquals(1250, $all->viewData('totalOverdue'));
        $this->assertCount(3, $all->viewData('groups'));

        $aidat = $this->get(route('reports.debt-list', ['due_type' => 'aidat']))->assertOk();
        $this->assertSame('Aidat Borç Listesi', $aidat->viewData('tableTitle'));
        $this->assertEquals(700, $aidat->viewData('totalOverdue'));
        $aidat->assertSee('Kısmi aidat')->assertSee('Malik aidatı')->assertDontSee('Demirbaş payı')->assertDontSee('Kapanmış borç');

        $malik = $this->get(route('reports.debt-list', ['due_type' => 'malik_payi']))->assertOk();
        $this->assertSame('Kat Maliki Payı Borç Listesi', $malik->viewData('tableTitle'));
        $this->assertEquals(550, $malik->viewData('totalOverdue'));
        $malik->assertSee('Kiracıdaki tarihsel malik payı')->assertSee('Demirbaş payı')->assertDontSee('Malik aidatı');
    }

    public function test_inactive_toggle_preserves_soft_deleted_debtors_without_extra_account_filters(): void
    {
        $hidden = $this->get(route('reports.debt-list'))->assertOk();
        $hidden->assertDontSee('>Hesap Adı</th>', false)->assertDontSee('>Gizli</td>', false)
            ->assertDontSee('Güncel Kiracı')->assertDontSee('Aktif Malik');
        $this->assertCount(3, $hidden->viewData('groups'));

        $active = $this->get(route('reports.debt-list', ['hide_account_names' => 0]))->assertOk();
        $active->assertSee('>Hesap Adı</th>', false)->assertSee('Güncel Kiracı')
            ->assertSee('Aktif Malik')->assertSee('İkinci Malik')->assertDontSee('Eski Kiracı');

        $withFormer = $this->get(route('reports.debt-list', ['show_inactive' => 1, 'hide_account_names' => 0]))->assertOk();
        $withFormer->assertSee('Eski Kiracı')->assertSee('Eski/Pasif Hesaplar Dahil')
            ->assertSee('show_inactive=1', false)->assertDontSee('name="account_filter"', false);
        $this->assertEquals(1450, $withFormer->viewData('totalOverdue'));
    }

    public function test_screen_pdf_and_excel_use_remaining_amounts_for_the_same_filtered_rows(): void
    {
        $filters = ['due_type' => 'aidat'];
        $screen = $this->get(route('reports.debt-list', $filters))->assertOk()
            ->assertSee('400,00 ₺')->assertDontSee('1.000,00 ₺');
        $this->assertEquals(700, $screen->viewData('totalOverdue'));

        $pdf = view('reports.debt-list-pdf', [
            'apartment' => $this->apartment,
            'groups' => $screen->viewData('groups'),
            'tableTitle' => $screen->viewData('tableTitle'),
            'totalOverdue' => $screen->viewData('totalOverdue'),
            'hideAccountNames' => $screen->viewData('hideAccountNames'),
        ])->render();
        $this->assertStringContainsString('400,00 ₺', $pdf);
        $this->assertStringNotContainsString('1.000,00 ₺', $pdf);
        $this->assertStringNotContainsString('Hesap Adı', $pdf);
        $this->assertStringNotContainsString('Gizli', $pdf);
        $this->assertStringNotContainsString('Güncel Kiracı', $pdf);

        $visiblePdf = view('reports.debt-list-pdf', [
            'apartment' => $this->apartment,
            'groups' => $screen->viewData('groups'),
            'tableTitle' => $screen->viewData('tableTitle'),
            'totalOverdue' => $screen->viewData('totalOverdue'),
            'hideAccountNames' => false,
        ])->render();
        $this->assertStringContainsString('Hesap Adı', $visiblePdf);
        $this->assertStringContainsString('Güncel Kiracı', $visiblePdf);

        $request = Request::create('/reports/debt-list/export/excel', 'GET', $filters);
        $response = app(ReportController::class)->overdue2Export(app(CurrentApartment::class), $request, 'excel');
        ob_start();
        try {
            ($response->getCallback())();
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $path = tempnam(sys_get_temp_dir(), 'debt-list-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            $this->assertSame('C', $sheet->getHighestColumn());
            $this->assertSame('Detaylar', $sheet->getCell('B3')->getValue());
            $this->assertStringContainsString('400,00 ₺', $sheet->getCell('B4')->getValue());
            $this->assertStringNotContainsString('1.000,00 ₺', $sheet->getCell('B4')->getValue());
            $this->assertEquals(400, $sheet->getCell('C4')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }

        $visibleRequest = Request::create('/reports/debt-list/export/excel', 'GET', [
            ...$filters,
            'hide_account_names' => 0,
        ]);
        $visibleResponse = app(ReportController::class)->overdue2Export(app(CurrentApartment::class), $visibleRequest, 'excel');
        ob_start();
        try {
            ($visibleResponse->getCallback())();
            $visibleBytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $visiblePath = tempnam(sys_get_temp_dir(), 'debt-list-visible-');
        try {
            file_put_contents($visiblePath, $visibleBytes);
            $visibleBook = IOFactory::load($visiblePath);
            $visibleSheet = $visibleBook->getActiveSheet();
            $this->assertSame('D', $visibleSheet->getHighestColumn());
            $this->assertSame('Hesap Adı', $visibleSheet->getCell('B3')->getValue());
            $this->assertSame('Güncel Kiracı', $visibleSheet->getCell('B4')->getValue());
            $visibleBook->disconnectWorksheets();
        } finally {
            unlink($visiblePath);
        }
    }
}
