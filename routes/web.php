<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountUserController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\ApartmentSelectionController;
use App\Http\Controllers\ApartmentSwitchController;
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
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Onboarding routes - no apartment required
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});

Route::middleware(['auth', 'apartment'])->group(function () {
    Route::get('current-apartment/select', ApartmentSelectionController::class)->name('current-apartment.select');
    Route::post('current-apartment', ApartmentSwitchController::class)->name('current-apartment.update');

    // Üye + Yönetici erişimi
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Giderler - Üye ve Yönetici erişimi (tüm resource actions)
    Route::get('expenses/import', [ExpenseController::class, 'importForm'])->name('expenses.import');
    Route::get('expenses/import/sample', [ExpenseController::class, 'importSample'])->name('expenses.import-sample');
    Route::post('expenses/import/preview', [ExpenseController::class, 'importPreview'])->name('expenses.import-preview');
    Route::get('expenses/import/preview', [ExpenseController::class, 'importPreviewPage'])->name('expenses.import-preview-page');
    Route::post('expenses/import/confirm', [ExpenseController::class, 'importConfirm'])->name('expenses.import-confirm');

    Route::resource('expenses', ExpenseController::class);
    Route::get('expenses/{expense}/payment', [ExpenseController::class, 'createPayment'])->name('expenses.payment.create');
    Route::post('expenses/{expense}/payment', [ExpenseController::class, 'storePayment'])->name('expenses.payment.store');
    Route::delete('expenses/{expense}/payment', [ExpenseController::class, 'destroyPayment'])->name('expenses.payment.destroy');

    // Aidatlar - Üye ve Yönetici erişimi
    Route::get('dues', [DueController::class, 'index'])->name('dues.index');
    Route::get('dues/create', [DueController::class, 'create'])->name('dues.create');
    Route::post('dues', [DueController::class, 'store'])->name('dues.store');
    Route::get('dues/expenses-by-period', [DueController::class, 'getExpensesForPeriod'])->name('dues.expenses.by-period');
    Route::get('dues/{due}', [DueController::class, 'show'])->name('dues.show');

    // Yönetici zorunlu
    Route::middleware('owner')->group(function () {

        Route::resource('apartments', ApartmentController::class);
        Route::resource('units', UnitController::class);
        Route::resource('accounts', AccountController::class);
        Route::get('accounts/{id}/statement', [AccountController::class, 'statement'])->name('accounts.statement');
        Route::get('accounts/{id}/statement/export', [AccountController::class, 'statementExport'])->name('accounts.statement.export');
        Route::get('accounts/statement/import-sample', [AccountController::class, 'statementImportSample'])->name('accounts.statement.import-sample');
        Route::post('accounts/{id}/statement/import', [AccountController::class, 'statementImport'])->name('accounts.statement.import');
        Route::get('accounts/{id}/statement/import-preview', [AccountController::class, 'statementImportPreview'])->name('accounts.statement.import-preview');
        Route::post('accounts/{id}/statement/import-confirm', [AccountController::class, 'statementImportConfirm'])->name('accounts.statement.import-confirm');
        Route::post('accounts/{id}/statement/delete-last-import', [AccountController::class, 'deleteLastImport'])->name('accounts.statement.delete-last-import');
        Route::delete('accounts/{id}/transactions/{transaction}', [AccountController::class, 'destroyTransaction'])->name('accounts.transactions.destroy');
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
        Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('dues/{due}/payment', [DueController::class, 'createPayment'])->name('dues.payment.create');
        Route::post('dues/{due}/payment', [DueController::class, 'storePayment'])->name('dues.payment.store');
        Route::post('accounts/{account}/dues/bulk-pay', [DueController::class, 'bulkPay'])->name('accounts.dues.bulk-pay');
        Route::get('payments/{payment}/allocations/create', [PaymentAllocationController::class, 'create'])->name('payments.allocations.create');
        Route::post('payments/{payment}/allocations', [PaymentAllocationController::class, 'store'])->name('payments.allocations.store');
        Route::delete('payments/{payment}/allocations/{allocation}', [PaymentAllocationController::class, 'destroy'])->name('payments.allocations.destroy');
        Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
        Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('due-plans/{duePlan}/generate-month', [DuePlanController::class, 'generateMonth'])->name('due-plans.generate-month');
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
