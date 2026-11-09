<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:reset-password {email}', function (string $email) {
    $user = \App\Models\User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error('Pengguna tidak ditemukan.');

        return 1;
    }
    $password = \Illuminate\Support\Str::password(16, symbols: false).'Aa1';
    $user->forceFill([
        'password' => \Illuminate\Support\Facades\Hash::make($password),
        'failed_login_attempts' => 0,
        'login_locked_until' => null,
    ])->save();
    $user->tokens()->delete();
    \App\Models\AuditLog::record('password.reset_cli', $user->id);
    $this->info("Password baru untuk {$user->email}: {$password}");
})->purpose('Buat password baru acak untuk pengguna (mis. admin yang lupa password)');
