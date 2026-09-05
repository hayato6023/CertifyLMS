<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 管理者お知らせの配信履歴。配信は不可逆で、編集 / 削除 / 再配信の動線を持たない。
 *
 * 関連: targetCertification(資格指定時) / targetUser(ユーザー指定時) / createdBy(配信者)
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_certification_id',
        'target_user_id',
        'created_by_user_id',
        'dispatched_count',
        'dispatched_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'dispatched_count' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'target_certification_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
