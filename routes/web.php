<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\ApartmentSelectionController;
use App\Http\Controllers\ApartmentSwitchController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminImpersonateController;
use App\Http\Controllers\Admin\AdminManagerController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Subscriber\SubscriberApartmentController;
use App\Http\Controllers\Subscriber\SubscriberApartmentCreateController;
use App\Http\Controllers\Subscriber\SubscriberDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashBoxController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\DuePlanController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PaymentAllocationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register/{package?}', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Onboarding routes - no apartment required
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    // Apartment creation - no apartment required
    Route::get('apartments/create', [ApartmentController::class, 'create'])->name('apartments.create');
    Route::post('apartments', [ApartmentController::class, 'store'])->name('apartments.store');
});

Route::middleware(['auth', 'apartment'])->group(function () {
    Route::get('current-apartment/select', ApartmentSelectionController::class)->name('current-apartment.select');
    Route::post('current-apartment', ApartmentSwitchController::class)->name('current-apartment.update');

    // Üye + Yönetici erişimi
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Raporlar - Tüm kullanıcılar
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('income-expense', [ReportController::class, 'incomeExpense'])->name('income-expense');
        Route::get('income-expense/export/{type}', [ReportController::class, 'incomeExpenseExport'])->name('income-expense.export');
        Route::get('debt-list', [ReportController::class, 'debtList'])->name('debt-list');
        Route::get('debt-list/export/{type}', [ReportController::class, 'debtListExport'])->name('debt-list.export');
        Route::get('receivable-list', [ReportController::class, 'receivableList'])->name('receivable-list');
        Route::get('receivable-list/export/{type}', [ReportController::class, 'receivableListExport'])->name('receivable-list.export');
        Route::get('account-statement', [ReportController::class, 'accountStatement'])->name('account-statement');
        Route::get('account-statement/export/{type}', [ReportController::class, 'accountStatementExport'])->name('account-statement.export');
        Route::get('due-collection', [ReportController::class, 'dueCollection'])->name('due-collection');
        Route::get('due-collection/export/{type}', [ReportController::class, 'dueCollectionExport'])->name('due-collection.export');
        Route::get('overdue', [ReportController::class, 'overdue'])->name('overdue');
        Route::get('overdue/export/{type}', [ReportController::class, 'overdueExport'])->name('overdue.export');
        Route::get('overdue2', [ReportController::class, 'overdue2'])->name('overdue2');
        Route::get('overdue2/export/{type}', [ReportController::class, 'overdue2Export'])->name('overdue2.export');
        Route::get('annual-activity', [ReportController::class, 'annualActivity'])->name('annual-activity');
        Route::get('annual-activity/export/{type}', [ReportController::class, 'annualActivityExport'])->name('annual-activity.export');
        Route::get('budget', [ReportController::class, 'budget'])->name('budget');
        Route::get('budget/export/{type}', [ReportController::class, 'budgetExport'])->name('budget.export');
        Route::get('monthly-board', [ReportController::class, 'monthlyBoard'])->name('monthly-board');
        Route::get('monthly-board/export/{type}', [ReportController::class, 'monthlyBoardExport'])->name('monthly-board.export');
    });

    // Giderler - Üye ve Yönetici erişimi (tüm resource actions)
    Route::resource('expenses', ExpenseController::class);
    Route::get('expenses/{expense}/payment', [ExpenseController::class, 'createPayment'])->name('expenses.payment.create');
    Route::post('expenses/{expense}/payment', [ExpenseController::class, 'storePayment'])->name('expenses.payment.store');
    Route::delete('expenses/{expense}/payment', [ExpenseController::class, 'destroyPayment'])->name('expenses.payment.destroy');

    // Aidatlar - Üye ve Yönetici erişimi
    Route::get('dues', [DueController::class, 'index'])->name('dues.index');
    Route::get('dues/export', [DueController::class, 'export'])->name('dues.export');
    Route::get('dues/create', [DueController::class, 'create'])->name('dues.create');
    Route::post('dues', [DueController::class, 'store'])->name('dues.store');
    Route::get('dues/expenses-by-period', [DueController::class, 'getExpensesForPeriod'])->name('dues.expenses.by-period');
    Route::get('dues/{due}', [DueController::class, 'show'])->name('dues.show');

    // Yönetici zorunlu
    Route::middleware('owner')->group(function () {

        Route::resource('apartments', ApartmentController::class);
        Route::post('apartments/{apartment}/destroy-all', [ApartmentController::class, 'destroyAll'])->name('apartments.destroy-all');
        Route::post('apartments/{apartment}/reset-and-renew', [ApartmentController::class, 'resetAndRenew'])->name('apartments.reset-and-renew');
        Route::resource('units', UnitController::class);

        // Bulk account import from Excel - MUST be before resource route
        Route::get('accounts/bulk-import', [AccountController::class, 'bulkImportForm'])->name('accounts.bulk-import');
        Route::get('accounts/bulk-import/sample', [AccountController::class, 'bulkImportSample'])->name('accounts.bulk-import-sample');
        Route::post('accounts/bulk-import/preview', [AccountController::class, 'bulkImportPreview'])->name('accounts.bulk-import-preview');
        Route::get('accounts/bulk-import/preview', [AccountController::class, 'bulkImportPreviewPage'])->name('accounts.bulk-import-preview-page');
        Route::post('accounts/bulk-import/confirm', [AccountController::class, 'bulkImportConfirm'])->name('accounts.bulk-import-confirm');

        Route::resource('accounts', AccountController::class);
        Route::get('accounts/{id}/statement', [AccountController::class, 'statement'])->name('accounts.statement');
        Route::get('accounts/{id}/statement/export', [AccountController::class, 'statementExport'])->name('accounts.statement.export');

        Route::delete('accounts/{id}/transactions/{transaction}', [AccountController::class, 'destroyTransaction'])->name('accounts.transactions.destroy');
        Route::post('accounts/imported-transactions', [AccountController::class, 'destroyAllImported'])->name('accounts.imported.destroy-all');
        Route::patch('accounts/{account}/terminate-tenancy', [AccountController::class, 'terminateTenancy'])->name('accounts.terminate-tenancy');
        Route::patch('accounts/{account}/terminate-ownership', [AccountController::class, 'terminateOwnership'])->name('accounts.terminate-ownership');
        Route::get('users', [AccountUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AccountUserController::class, 'create'])->name('users.create');
        Route::get('users/lookup', [AccountUserController::class, 'lookupEmail'])->name('users.lookup');
        Route::post('users', [AccountUserController::class, 'storeUser'])->name('users.store');
        Route::get('users/{user}', [AccountUserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit', [AccountUserController::class, 'edit'])->name('users.edit');
        Route::patch('users/{user}', [AccountUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/password', [AccountUserController::class, 'updatePassword'])->name('users.password');
        Route::post('accounts/{account}/user', [AccountUserController::class, 'store'])->name('accounts.user.store');
        Route::post('users/{user}/attach-accounts', [AccountUserController::class, 'attachAccounts'])->name('users.attach-accounts');
        Route::patch('accounts/{account}/user/role', [AccountUserController::class, 'updateRole'])->name('accounts.user.role');
        Route::patch('users/{user}/role', [AccountUserController::class, 'updateUserRole'])->name('users.role');
        Route::patch('users/{user}/toggle-active', [AccountUserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::delete('accounts/{account}/user', [AccountUserController::class, 'destroy'])->name('accounts.user.destroy');
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::get('payments/preview-allocations', [PaymentController::class, 'previewAllocations'])->name('payments.preview-allocations');
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('dues/{due}/payment', [DueController::class, 'createPayment'])->name('dues.payment.create');
        Route::post('dues/{due}/payment', [DueController::class, 'storePayment'])->name('dues.payment.store');
        Route::post('accounts/{account}/dues/bulk-pay', [DueController::class, 'bulkPay'])->name('accounts.dues.bulk-pay');
        Route::get('payments/{payment}/allocations/create', [PaymentAllocationController::class, 'create'])->name('payments.allocations.create');
        Route::post('payments/{payment}/allocations', [PaymentAllocationController::class, 'store'])->name('payments.allocations.store');
        Route::get('payments/{payment}/supplier-allocations/create', [PaymentAllocationController::class, 'supplierCreate'])->name('payments.supplier-allocations.create');
        Route::post('payments/{payment}/supplier-allocations', [PaymentAllocationController::class, 'supplierStore'])->name('payments.supplier-allocations.store');
        Route::delete('payments/{payment}/allocations/{allocation}', [PaymentAllocationController::class, 'destroy'])->name('payments.allocations.destroy');
        Route::match(['get', 'post'], 'accounts/{account}/payments/multi-allocate', [PaymentAllocationController::class, 'multiCreate'])->name('accounts.payments.multi-allocate');
        Route::post('accounts/{account}/payments/multi-allocate/store', [PaymentAllocationController::class, 'multiStore'])->name('accounts.payments.multi-allocate.store');
        Route::match(['get', 'post'], 'accounts/{account}/payments/multi-supplier-allocate', [PaymentAllocationController::class, 'multiSupplierCreate'])->name('accounts.payments.multi-supplier-allocate');
        Route::post('accounts/{account}/payments/multi-supplier-allocate/store', [PaymentAllocationController::class, 'multiSupplierStore'])->name('accounts.payments.multi-supplier-allocate.store');
        Route::get('accounts/{account}/payment/create', [AccountController::class, 'createSupplierPayment'])->name('accounts.supplier-payment.create');
        Route::get('accounts/{account}/payment/preview-allocations', [AccountController::class, 'previewSupplierAllocations'])->name('accounts.supplier-payment.preview-allocations');
        Route::post('accounts/{account}/payment', [AccountController::class, 'storeSupplierPayment'])->name('accounts.supplier-payment.store');
        Route::post('accounts/{account}/expenses/multi-pay', [AccountController::class, 'multiPayExpenses'])->name('accounts.expenses.multi-pay');
        Route::post('accounts/{account}/expenses/multi-pay/store', [AccountController::class, 'storeMultiPayExpenses'])->name('accounts.expenses.multi-pay.store');
        Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('due-plans/{duePlan}/generate-month', [DuePlanController::class, 'generateMonth'])->name('due-plans.generate-month');
        Route::patch('due-plans/{duePlan}/deactivate', [DuePlanController::class, 'deactivate'])->name('due-plans.deactivate');
        Route::resource('due-plans', DuePlanController::class);
        Route::get('dues/batch/create', [DueController::class, 'createBatch'])->name('dues.batch.create');
        Route::delete('dues/bulk-destroy', [DueController::class, 'bulkDestroy'])->name('dues.bulk-destroy');
        Route::get('dues/{due}/edit', [DueController::class, 'edit'])->name('dues.edit');
        Route::patch('dues/{due}', [DueController::class, 'update'])->name('dues.update');
        Route::delete('dues/{due}', [DueController::class, 'destroy'])->name('dues.destroy');
        Route::post('dues/{due}/transfer', [DueController::class, 'transfer'])->name('dues.transfer');
        Route::get('supplier-refunds/create', [PaymentController::class, 'createSupplierRefund'])->name('supplier-refunds.create');
        Route::post('supplier-refunds', [PaymentController::class, 'storeSupplierRefund'])->name('supplier-refunds.store');
        Route::resource('cash-boxes', CashBoxController::class)->except(['index']);
        Route::resource('cash', CashController::class);
    });
});

Route::post('admin/impersonate/leave', [AdminImpersonateController::class, 'leave'])
    ->name('admin.impersonate.leave')
    ->middleware('auth');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('managers', [AdminManagerController::class, 'index'])->name('managers.index');
    Route::get('managers/{manager}', [AdminManagerController::class, 'show'])->name('managers.show');
    Route::patch('managers/{manager}/subscription', [AdminManagerController::class, 'updateSubscription'])->name('managers.subscription.update');
    Route::patch('managers/{manager}/quota', [AdminManagerController::class, 'updateQuota'])->name('managers.quota.update');
    Route::post('managers/{manager}/trial-extend', [AdminManagerController::class, 'extendTrial'])->name('managers.trial.extend');

    Route::resource('packages', AdminPackageController::class);
    Route::patch('packages/{package}/features', [AdminPackageController::class, 'updateFeatures'])->name('packages.features.update');

    Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    Route::resource('admin-users', AdminUserController::class)
        ->except(['show'])
        ->middleware('super_admin');

    Route::post('impersonate/{manager}', [AdminImpersonateController::class, 'start'])->name('impersonate.start');
});

Route::prefix('subscriber')->name('subscriber.')->middleware(['auth', 'subscriber'])->group(function () {
    Route::get('/', SubscriberDashboardController::class)->name('dashboard');

    Route::get('apartments', [SubscriberApartmentController::class, 'index'])->name('apartments.index');
    Route::post('apartment', [SubscriberApartmentController::class, 'update'])->name('apartment.update');

    // Apartment creation for subscribers - no apartment required
    Route::get('apartments/create', [SubscriberApartmentCreateController::class, 'create'])->name('apartments.create');
    Route::post('apartments', [SubscriberApartmentCreateController::class, 'store'])->name('apartments.store');

    // Apartment editing for subscribers
    Route::get('apartments/{apartment}/edit', [ApartmentController::class, 'edit'])->name('apartments.edit');
    Route::put('apartments/{apartment}', [ApartmentController::class, 'update'])->name('apartments.update');
});
