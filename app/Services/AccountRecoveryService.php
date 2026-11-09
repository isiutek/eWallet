<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Pemulihan akun oleh admin: reset password, PIN, dan 2FA. */
class AccountRecoveryService
{
    /** @return string password sementara (ditampilkan sekali ke admin) */
    public function resetPassword(User $user, ?int $actorId = null): string
    {
        $temp = Str::password(10, symbols: false).'Aa1';
        $user->forceFill([
            'password' => Hash::make($temp),
            'must_change_password' => true,
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ])->save();
        $this->logoutEverywhere($user);
        AuditLog::record('admin.user.password_reset', $actorId, ['target' => $user->id, 'email' => $user->email]);

        return $temp;
    }

    public function resetPin(User $user, ?int $actorId = null): void
    {
        $user->forceFill(['pin_hash' => null, 'failed_pin_attempts' => 0, 'pin_locked_until' => null])->save();
        AuditLog::record('admin.user.pin_reset', $actorId, ['target' => $user->id, 'email' => $user->email]);
    }

    public function resetTwoFactor(User $user, ?int $actorId = null): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->logoutEverywhere($user);
        AuditLog::record('admin.user.2fa_reset', $actorId, ['target' => $user->id, 'email' => $user->email]);
    }

    /** Cabut semua sesi web & token API milik pengguna. */
    private function logoutEverywhere(User $user): void
    {
        $user->tokens()->delete();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
