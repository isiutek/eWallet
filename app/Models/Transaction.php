<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const CREDIT = 'credit';
    public const DEBIT = 'debit';

    public const TYPES = [
        'topup' => 'Isi Saldo',
        'transfer' => 'Transfer',
        'qr_payment' => 'Pembayaran QR',
        'adjustment' => 'Penyesuaian',
    ];

    protected $fillable = [
        'reference', 'wallet_id', 'type', 'direction', 'amount', 'balance_before', 'balance_after',
        'counterparty_wallet_id', 'status', 'description', 'meta', 'idempotency_key',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counterparty_wallet_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isCredit(): bool
    {
        return $this->direction === self::CREDIT;
    }
}
