<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Verifikasi kredensial dengan penguncian akun setelah gagal berulang (dipakai web & API). */
class AuthService
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 15;

    public function attempt(string $email, string $password): User
    {
        $user = User::where('email', strtolower($email))->first();

        if ($user && $user->login_locked_until && $user->login_locked_until->isFuture()) {
            throw ValidationException::withMessages(['email' => 'Akun terkunci sementara karena terlalu banyak percobaan. Coba lagi setelah '.$user->login_locked_until->format('H:i').'.']);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->forceFill($attempts >= self::MAX_ATTEMPTS
                    ? ['failed_login_attempts' => 0, 'login_locked_until' => now()->addMinutes(self::LOCK_MINUTES)]
                    : ['failed_login_attempts' => $attempts])->save();
                AuditLog::record('login.failed', $user->id, ['attempts' => $attempts]);
            }
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Akun Anda dinonaktifkan. Hubungi admin.']);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->save();

        return $user;
    }
}
