<?php

namespace Tests\Feature;

use App\Services\QrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QrPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pay($payer, string $payload, ?int $amount = null)
    {
        return $this->actingAs($payer)->post('/qr/pay', array_filter([
            'payload' => $payload, 'amount' => $amount, 'pin' => '246810', 'idempotency_key' => (string) Str::uuid(),
        ]));
    }

    public function test_dynamic_qr_is_paid_once(): void
    {
        $merchant = $this->userWithBalance(0);
        $payer = $this->userWithBalance(100000);
        $qr = app(QrService::class);
        $payload = $qr->payload($qr->createDynamic($merchant->wallet, 25000, 'Kopi'));

        $this->actingAs($payer)->get('/qr/confirm?payload='.urlencode($payload))->assertOk()->assertSee('Rp 25.000');
        $this->pay($payer, $payload)->assertRedirect();
        $this->pay($payer, $payload)->assertSessionHasErrors('wallet');

        $this->assertSame(75000, $payer->wallet->fresh()->balance);
        $this->assertSame(25000, $merchant->wallet->fresh()->balance);
    }

    public function test_static_qr_uses_payer_amount(): void
    {
        $merchant = $this->userWithBalance(0);
        $payer = $this->userWithBalance(100000);
        $qr = app(QrService::class);
        $payload = $qr->payload($qr->staticFor($merchant->wallet));

        $this->pay($payer, $payload, 15000)->assertRedirect();
        $this->pay($payer, $payload, 5000)->assertRedirect();

        $this->assertSame(20000, $merchant->wallet->fresh()->balance);
    }

    public function test_tampered_or_expired_qr_is_rejected(): void
    {
        $merchant = $this->userWithBalance(0);
        $payer = $this->userWithBalance(100000);
        $qr = app(QrService::class);
        $pr = $qr->createDynamic($merchant->wallet, 25000, null);
        $payload = $qr->payload($pr);

        $this->pay($payer, substr($payload, 0, -2).'xx')->assertSessionHasErrors('wallet');

        $pr->update(['expires_at' => now()->subMinute()]);
        $this->pay($payer, $payload)->assertSessionHasErrors('wallet');
        $this->assertSame(100000, $payer->wallet->fresh()->balance);
    }
}
