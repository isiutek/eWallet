<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'ip', 'user_agent', 'meta', 'created_at'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?int $userId = null, array $meta = []): void
    {
        $request = request();
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
