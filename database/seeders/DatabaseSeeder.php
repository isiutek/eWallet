<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PinService;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = app(WalletService::class);
        $pins = app(PinService::class);

        $admin = User::firstOrCreate(['email' => 'admin@ewallet.test'], ['name' => 'Administrator', 'password' => 'Admin12345']);
        $admin->forceFill(['role' => 'admin'])->save();
        $wallets->createFor($admin);

        if (app()->environment('local')) {
            foreach ([['Budi Santoso', 'budi@ewallet.test'], ['Siti Aminah', 'siti@ewallet.test']] as [$name, $email]) {
                $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'Password123']);
                $w = $wallets->createFor($u);
                if (! $u->hasPin()) {
                    $pins->set($u, '246810');
                }
                if ($w->balance === 0) {
                    $wallets->credit($w, 1000000, 'topup', 'Saldo awal demo');
                }
            }
        }
    }
}
