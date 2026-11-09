<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private function send($from, $to, int $amount, string $pin = '246810', ?string $key = null)
    {
        return $this->actingAs($from)->post('/transfer', [
            'to' => $to->wallet->number, 'amount' => $amount, 'pin' => $pin,
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    public function test_transfer_moves_balance_and_writes_double_entry(): void
    {
        $a = $this->userWithBalance(100000);
        $b = $this->userWithBalance(0);

        $this->send($a, $b, 40000)->assertRedirect();

        $this->assertSame(60000, $a->wallet->fresh()->balance);
        $this->assertSame(40000, $b->wallet->fresh()->balance);
        $debit = Transaction::where('wallet_id', $a->wallet->id)->where('direction', 'debit')->first();
        $credit = Transaction::where('wallet_id', $b->wallet->id)->where('direction', 'credit')->first();
        $this->assertSame($debit->reference, $credit->reference);
        $this->assertSame(100000, $debit->balance_before);
        $this->assertSame(60000, $debit->balance_after);
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        $a = $this->userWithBalance(10000);
        $b = $this->userWithBalance(0);

        $this->send($a, $b, 20000)->assertSessionHasErrors('wallet');
        $this->assertSame(10000, $a->wallet->fresh()->balance);
        $this->assertSame(0, $b->wallet->fresh()->balance);
    }

    public function test_same_idempotency_key_only_transfers_once(): void
    {
        $a = $this->userWithBalance(100000);
        $b = $this->userWithBalance(0);
        $key = (string) Str::uuid();

        $this->send($a, $b, 10000, key: $key);
        $this->send($a, $b, 10000, key: $key);

        $this->assertSame(90000, $a->wallet->fresh()->balance);
        $this->assertSame(10000, $b->wallet->fresh()->balance);
    }

    public function test_wrong_pin_blocks_transfer_and_locks_after_five_attempts(): void
    {
        $a = $this->userWithBalance(100000);
        $b = $this->userWithBalance(0);

        for ($i = 0; $i < 5; $i++) {
            $this->send($a, $b, 1000, '999999')->assertSessionHasErrors('wallet');
        }
        $this->assertNotNull($a->fresh()->pin_locked_until);

        // PIN benar pun ditolak saat terkunci
        $this->send($a->fresh(), $b, 1000)->assertSessionHasErrors('wallet');
        $this->assertSame(100000, $a->wallet->fresh()->balance);
    }

    public function test_daily_limit_and_self_transfer(): void
    {
        Setting::put('daily_debit_limit', 50000);
        $a = $this->userWithBalance(200000);
        $b = $this->userWithBalance(0);

        $this->send($a, $b, 40000);
        $this->send($a, $b, 20000)->assertSessionHasErrors('wallet');
        $this->send($a, $a, 1000)->assertSessionHasErrors('wallet');
        $this->assertSame(160000, $a->wallet->fresh()->balance);
    }

    public function test_user_cannot_view_other_users_transaction(): void
    {
        $a = $this->userWithBalance(5000);
        $b = $this->userWithBalance(0);
        $trx = Transaction::where('wallet_id', $a->wallet->id)->first();

        $this->actingAs($b)->get('/transactions/'.$trx->id)->assertNotFound();
        $this->actingAs($a)->get('/transactions/'.$trx->id)->assertOk();
    }

    public function test_report_page_and_csv_export(): void
    {
        $a = $this->userWithBalance(75000);
        $this->actingAs($a)->get('/dashboard')->assertOk()->assertSee('Rp 75.000');
        $this->actingAs($a)->get('/transactions')->assertOk()->assertSee('Saldo awal');

        $csv = $this->actingAs($a)->get('/transactions?export=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Saldo awal', $csv->streamedContent());
    }
}
