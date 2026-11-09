<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $attributes = ['role' => 'user', 'status' => 'active', 'failed_pin_attempts' => 0, 'failed_login_attempts' => 0];

    protected $hidden = ['password', 'remember_token', 'pin_hash', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'login_locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPin(): bool
    {
        return ! empty($this->pin_hash);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }
}
