<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Pengguna yang passwordnya direset admin wajib mengganti password sebelum memakai aplikasi. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->must_change_password) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password harus diganti terlebih dahulu.'], 403);
            }
            if (! $request->routeIs('security.index', 'security.password', 'logout')) {
                return redirect()->route('security.index')
                    ->withErrors(['password' => 'Password Anda direset oleh admin. Silakan buat password baru terlebih dahulu.']);
            }
        }

        return $next($request);
    }
}
