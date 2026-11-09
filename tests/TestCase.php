<?php

namespace Tests;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function userWithBalance(int $balance = 0, array $attrs = []): User
    {
        $user = User::factory()->withPin()->create($attrs);
        $wallet = app(WalletService::class)->createFor($user);
        if ($balance > 0) {
            app(WalletService::class)->credit($wallet, $balance, 'topup', 'Saldo awal');
        }

        return $user->fresh('wallet');
    }
}
