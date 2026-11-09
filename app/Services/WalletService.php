<?php

namespace App\Services;

use App\Exceptions\WalletException;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu untuk mengubah saldo.
 * Setiap mutasi: DB transaction + row lock, ledger tidak bisa diubah, idempotency key.
 */
class WalletService
{
    public function createFor(User $user): Wallet
    {
        return $user->wallet ?? $user->wallet()->create(['number' => Wallet::generateNumber()]);
    }

    public static function newReference(string $prefix = 'TRX'): string
    {
        return $prefix.now()->format('ymdHis').strtoupper(Str::random(6));
    }

    /** Menambah saldo (mis. isi saldo). */
    public function credit(Wallet $wallet, int $amount, string $type, string $description, array $meta = [], ?string $reference = null, ?string $idempotencyKey = null): Transaction
    {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($wallet, $amount, $type, $description, $meta, $reference, $idempotencyKey) {
            if ($existing = $this->findIdempotent($wallet->id, $idempotencyKey)) {
                return $existing;
            }
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $this->assertActive($locked);

            return $this->post($locked, Transaction::CREDIT, $amount, $type, $description, $meta, $reference ?? self::newReference(), null, $idempotencyKey);
        });
    }

    /**
     * Transfer saldo antar dompet (dipakai untuk transfer biasa dan pembayaran QR).
     *
     * @return array{0: Transaction, 1: Transaction} [debit pengirim, credit penerima]
     */
    public function transfer(Wallet $from, Wallet $to, int $amount, string $type = 'transfer', ?string $note = null, array $meta = [], ?string $idempotencyKey = null): array
    {
        $this->assertPositive($amount);
        if ($from->id === $to->id) {
            throw new WalletException('Tidak bisa mentransfer ke dompet sendiri.');
        }
        $min = (int) Setting::get('transfer_min');
        if ($amount < $min) {
            throw new WalletException('Nominal minimal transfer Rp '.number_format($min, 0, ',', '.').'.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $type, $note, $meta, $idempotencyKey) {
            if ($existing = $this->findIdempotent($from->id, $idempotencyKey)) {
                $pair = Transaction::where('reference', $existing->reference)->where('direction', Transaction::CREDIT)->first();

                return [$existing, $pair];
            }

            // Kunci kedua dompet dengan urutan id yang konsisten agar tidak deadlock.
            $locked = Wallet::whereIn('id', [$from->id, $to->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $src = $locked[$from->id];
            $dst = $locked[$to->id];
            $this->assertActive($src);
            $this->assertActive($dst);

            if ($src->balance < $amount) {
                throw new WalletException('Saldo tidak mencukupi.');
            }
            $this->assertDailyLimit($src, $amount);

            $reference = self::newReference($type === 'qr_payment' ? 'QRP' : 'TRF');
            $srcName = $src->user->name;
            $dstName = $dst->user->name;

            $debit = $this->post($src, Transaction::DEBIT, $amount, $type,
                ($type === 'qr_payment' ? 'Bayar QR ke ' : 'Transfer ke ').$dstName.($note ? " - $note" : ''),
                $meta, $reference, $dst->id, $idempotencyKey);
            $credit = $this->post($dst, Transaction::CREDIT, $amount, $type,
                ($type === 'qr_payment' ? 'Terima pembayaran QR dari ' : 'Terima transfer dari ').$srcName.($note ? " - $note" : ''),
                $meta, $reference, $src->id, null);

            return [$debit, $credit];
        });
    }

    public function todayDebitTotal(Wallet $wallet): int
    {
        return (int) Transaction::where('wallet_id', $wallet->id)
            ->where('direction', Transaction::DEBIT)
            ->where('status', 'success')
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');
    }

    private function post(Wallet $wallet, string $direction, int $amount, string $type, string $description, array $meta, string $reference, ?int $counterpartyId, ?string $idempotencyKey): Transaction
    {
        $before = $wallet->balance;
        $after = $direction === Transaction::CREDIT ? $before + $amount : $before - $amount;
        if ($after < 0) {
            throw new WalletException('Saldo tidak mencukupi.');
        }

        $wallet->balance = $after;
        $wallet->save();

        return Transaction::create([
            'reference' => $reference,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'counterparty_wallet_id' => $counterpartyId,
            'status' => 'success',
            'description' => mb_substr($description, 0, 255),
            'meta' => $meta ?: null,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function findIdempotent(int $walletId, ?string $key): ?Transaction
    {
        return $key ? Transaction::where('wallet_id', $walletId)->where('idempotency_key', $key)->first() : null;
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new WalletException('Nominal harus lebih dari nol.');
        }
    }

    private function assertActive(Wallet $wallet): void
    {
        if ($wallet->status !== 'active' || $wallet->user->status !== 'active') {
            throw new WalletException('Dompet sedang dibekukan.');
        }
    }

    private function assertDailyLimit(Wallet $wallet, int $amount): void
    {
        $limit = (int) Setting::get('daily_debit_limit');
        if ($limit > 0 && $this->todayDebitTotal($wallet) + $amount > $limit) {
            throw new WalletException('Melebihi batas transaksi harian Rp '.number_format($limit, 0, ',', '.').'.');
        }
    }
}
