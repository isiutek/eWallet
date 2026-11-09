<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'number', 'balance', 'currency', 'status'];

    protected $attributes = ['balance' => 0, 'currency' => 'IDR', 'status' => 'active'];

    protected $casts = ['balance' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public static function generateNumber(): string
    {
        do {
            $number = '88'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (static::where('number', $number)->exists());

        return $number;
    }
}
