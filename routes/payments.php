<?php

use App\Http\Controllers\PaytrCallbackController;
use Illuminate\Support\Facades\Route;

// Intentionally outside web/auth/CSRF/session middleware; the provider HMAC authenticates this POST.
Route::post('payments/paytr/callback', PaytrCallbackController::class)->name('payments.paytr.callback');
