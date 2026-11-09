<?php

use App\Exceptions\WalletException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            '2fa' => \App\Http\Middleware\EnsureTwoFactorPassed::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'active' => \App\Http\Middleware\EnsureAccountActive::class,
        ]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(WalletException::class);
        $exceptions->render(function (WalletException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput($request->except(['pin', 'password']))->withErrors(['wallet' => $e->getMessage()]);
        });
    })->create();
