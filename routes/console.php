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
    $password = app(\App\Services\AccountRecoveryService::class)->resetPassword($user);
    $this->info("Password sementara untuk {$user->email}: {$password}");
    $this->line('Pengguna wajib mengganti password ini setelah login.');
})->purpose('Buat password baru acak untuk pengguna (mis. admin yang lupa password)');
