<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topup extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'wallet_id', 'amount', 'method', 'status', 'proof_path', 'sender_name',
        'xendit_invoice_id', 'xendit_invoice_url', 'payment_channel', 'processed_by', 'admin_note',
        'paid_at', 'expires_at',
    ];

    protected $casts = ['amount' => 'integer', 'paid_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
