<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\ApartmentSelectionController;
use App\Http\Controllers\ApartmentSwitchController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashBoxController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('current-apartment/select', ApartmentSelectionController::class)->name('current-apartment.select');
    Route::post('current-apartment', ApartmentSwitchController::class)->name('current-apartment.update');
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('apartments', ApartmentController::class);
    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::get('dues/{due}/payment', [DueController::class, 'createPayment'])->name('dues.payment.create');
    Route::post('dues/{due}/payment', [DueController::class, 'storePayment'])->name('dues.payment.store');
    Route::resource('dues', DueController::class);
    Route::get('expenses/{expense}/payment', [ExpenseController::class, 'createPayment'])->name('expenses.payment.create');
    Route::post('expenses/{expense}/payment', [ExpenseController::class, 'storePayment'])->name('expenses.payment.store');
    Route::resource('expenses', ExpenseController::class);
    Route::resource('cash-boxes', CashBoxController::class)->except(['index', 'show']);
    Route::resource('cash', CashController::class);
});
