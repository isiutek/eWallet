<?php

namespace App\Services;

use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/** PIN transaksi 6 digit, terpisah dari password, dengan penguncian setelah gagal berulang. */
class PinService
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_MINUTES = 30;

    public function set(User $user, string $pin): void
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw new WalletException('PIN harus 6 digit angka.');
        }
        if (preg_match('/^(\d)\1{5}$/', $pin) || in_array($pin, ['123456', '654321', '012345'], true)) {
            throw new WalletException('PIN terlalu mudah ditebak.');
        }
        $user->forceFill(['pin_hash' => Hash::make($pin), 'failed_pin_attempts' => 0, 'pin_locked_until' => null])->save();
        AuditLog::record('pin.set', $user->id);
    }

    /** @throws WalletException bila PIN salah / terkunci */
    public function verify(User $user, ?string $pin): void
    {
        if (! $user->hasPin()) {
            throw new WalletException('Silakan buat PIN transaksi terlebih dahulu.');
        }
        if ($user->pin_locked_until && $user->pin_locked_until->isFuture()) {
            throw new WalletException('PIN terkunci sampai '.$user->pin_locked_until->format('H:i').'.');
        }
        if ($pin !== null && Hash::check($pin, $user->pin_hash)) {
            if ($user->failed_pin_attempts > 0) {
                $user->forceFill(['failed_pin_attempts' => 0, 'pin_locked_until' => null])->save();
            }

            return;
        }

        $attempts = $user->failed_pin_attempts + 1;
        $data = ['failed_pin_attempts' => $attempts];
        if ($attempts >= self::MAX_ATTEMPTS) {
            $data = ['failed_pin_attempts' => 0, 'pin_locked_until' => now()->addMinutes(self::LOCK_MINUTES)];
        }
        $user->forceFill($data)->save();
        AuditLog::record('pin.failed', $user->id, ['attempts' => $attempts]);

        $left = self::MAX_ATTEMPTS - $attempts;
        throw new WalletException($left > 0 ? "PIN salah. Sisa percobaan: $left." : 'PIN salah 5 kali, PIN terkunci '.self::LOCK_MINUTES.' menit.');
    }
}
