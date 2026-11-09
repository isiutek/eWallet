<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    protected $fillable = ['code', 'wallet_id', 'amount', 'description', 'single_use', 'expires_at', 'paid_at', 'paid_reference'];

    protected $casts = ['amount' => 'integer', 'single_use' => 'boolean', 'expires_at' => 'datetime', 'paid_at' => 'datetime'];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isUsable(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return ! ($this->single_use && $this->paid_at);
    }
}
