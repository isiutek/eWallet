<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorPassed
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->hasTwoFactor() && ! $request->session()->get('2fa_passed')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
