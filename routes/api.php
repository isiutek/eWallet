<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [ApiController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'password.changed', 'throttle:api'])->group(function () {
        Route::post('/logout', [ApiController::class, 'logout']);
        Route::get('/me', [ApiController::class, 'me']);
        Route::get('/transactions', [ApiController::class, 'transactions']);
        Route::post('/transfers', [ApiController::class, 'transfer'])->middleware('throttle:money');
        Route::get('/topups', [ApiController::class, 'topups']);
        Route::post('/topups', [ApiController::class, 'createTopup'])->middleware('throttle:money');
        Route::get('/qr/static', [ApiController::class, 'qrStatic']);
        Route::post('/qr/dynamic', [ApiController::class, 'qrDynamic']);
        Route::post('/qr/resolve', [ApiController::class, 'qrResolve']);
        Route::post('/qr/pay', [ApiController::class, 'qrPay'])->middleware('throttle:money');
    });
});
