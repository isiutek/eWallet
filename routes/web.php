<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::get('/two-factor', [AuthController::class, 'showTwoFactor'])->name('two-factor.challenge');
    Route::post('/two-factor', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'active', '2fa', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transfer', [TransferController::class, 'create'])->name('transfer.create');
    Route::get('/transfer/lookup', [TransferController::class, 'lookup'])->name('transfer.lookup')->middleware('throttle:30,1');
    Route::post('/transfer', [TransferController::class, 'store'])->name('transfer.store')->middleware('throttle:money');

    Route::get('/topup', [TopupController::class, 'index'])->name('topup.index');
    Route::post('/topup', [TopupController::class, 'store'])->name('topup.store')->middleware('throttle:money');

    Route::get('/qr/receive', [QrController::class, 'receive'])->name('qr.receive');
    Route::post('/qr/receive', [QrController::class, 'createDynamic'])->name('qr.dynamic');
    Route::get('/qr/status/{code}', [QrController::class, 'status'])->name('qr.status');
    Route::get('/qr/scan', [QrController::class, 'scan'])->name('qr.scan');
    Route::get('/qr/confirm', [QrController::class, 'confirm'])->name('qr.confirm');
    Route::post('/qr/pay', [QrController::class, 'pay'])->name('qr.pay')->middleware('throttle:money');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/pin', [SecurityController::class, 'setPin'])->name('security.pin')->middleware('throttle:5,1');
    Route::post('/security/2fa/start', [SecurityController::class, 'startTwoFactor'])->name('security.2fa.start');
    Route::post('/security/2fa/confirm', [SecurityController::class, 'confirmTwoFactor'])->name('security.2fa.confirm')->middleware('throttle:5,1');
    Route::post('/security/2fa/disable', [SecurityController::class, 'disableTwoFactor'])->name('security.2fa.disable');
    Route::post('/security/password', [SecurityController::class, 'changePassword'])->name('security.password')->middleware('throttle:5,1');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/topups', [AdminController::class, 'topups'])->name('topups');
        Route::get('/topups/{topup}/proof', [AdminController::class, 'proof'])->name('topups.proof');
        Route::post('/topups/{topup}/approve', [AdminController::class, 'approve'])->name('topups.approve');
        Route::post('/topups/{topup}/reject', [AdminController::class, 'reject'])->name('topups.reject');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings', [AdminController::class, 'updateSettings']);
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/reset-pin', [AdminController::class, 'resetPin'])->name('users.reset-pin');
        Route::post('/users/{user}/reset-2fa', [AdminController::class, 'resetTwoFactor'])->name('users.reset-2fa');
        Route::get('/transactions', [AdminController::class, 'transactions'])->name('transactions');
        Route::get('/audit', [AdminController::class, 'auditLogs'])->name('audit');
    });
});

Route::post('/webhooks/xendit/invoice', [WebhookController::class, 'xenditInvoice'])
    ->middleware('throttle:webhook')->name('webhooks.xendit');
