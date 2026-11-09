<?php

namespace Tests\Feature;

use App\Services\QrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_transfer_and_qr_via_api(): void
    {
        $a = $this->userWithBalance(100000, ['email' => 'api@example.com']);
        $b = $this->userWithBalance(0);

        $token = $this->postJson('/api/v1/login', ['email' => 'api@example.com', 'password' => 'Password123', 'device_name' => 'android'])
            ->assertOk()->json('token');
        $h = ['Authorization' => "Bearer $token"];

        $this->getJson('/api/v1/me', $h)->assertOk()->assertJsonPath('wallet.balance', 100000);

        $this->postJson('/api/v1/transfers', ['to' => $b->wallet->number, 'amount' => 30000, 'pin' => '246810'], $h)
            ->assertStatus(422); // tanpa Idempotency-Key
        $h2 = $h + ['Idempotency-Key' => 'key-transfer-0001'];
        $this->postJson('/api/v1/transfers', ['to' => $b->wallet->number, 'amount' => 30000, 'pin' => '246810'], $h2)->assertCreated();
        $this->postJson('/api/v1/transfers', ['to' => $b->wallet->number, 'amount' => 30000, 'pin' => '246810'], $h2)->assertCreated();
        $this->assertSame(70000, $a->wallet->fresh()->balance);

        $qr = app(QrService::class);
        $payload = $qr->payload($qr->createDynamic($b->wallet, 20000, 'Tes'));
        $this->postJson('/api/v1/qr/resolve', ['payload' => $payload], $h)->assertOk()->assertJsonPath('amount', 20000);
        $this->postJson('/api/v1/qr/pay', ['payload' => $payload, 'pin' => '246810'], $h + ['Idempotency-Key' => 'key-qr-00001'])->assertCreated();
        $this->assertSame(50000, $a->wallet->fresh()->balance);

        $this->getJson('/api/v1/transactions', $h)->assertOk()->assertJsonPath('summary.out', 50000);
    }
}
