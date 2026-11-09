<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /** Nilai default bila belum diatur admin. */
    public const DEFAULTS = [
        'topup_mode' => 'both',            // manual | xendit | both
        'topup_min' => 10000,
        'topup_max' => 10000000,
        'transfer_min' => 1000,
        'daily_debit_limit' => 20000000,   // batas total uang keluar per hari
        'pin_required_above' => 0,         // PIN wajib untuk semua transaksi keluar
        'manual_bank_info' => "Bank BCA 1234567890 a.n. PT Dompet Digital",
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
