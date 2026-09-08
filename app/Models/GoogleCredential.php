<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチの Google カレンダー連携トークン(1 コーチ 1 連携)。
 */
class GoogleCredential extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'calendar_id',
        'token_expires_at',
        'connected_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
