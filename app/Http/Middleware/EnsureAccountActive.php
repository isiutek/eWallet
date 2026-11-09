<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->status !== 'active') {
            if ($request->expectsJson()) {
                $request->user()->currentAccessToken()?->delete();

                return response()->json(['message' => 'Akun dinonaktifkan.'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi admin.']);
        }

        return $next($request);
    }
}
