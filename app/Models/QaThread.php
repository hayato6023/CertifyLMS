<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板(Q&A)のスレッド。受講生が公開資格を選んで投稿する質問。
 *
 * status は open / resolved の 2 値。解決済への遷移で resolved_at を打刻する。
 * 関連: Certification / User(投稿者) / QaReply(回答, HasMany)
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    /**
     * 指定した資格 ID 群に属するスレッドに絞り込む。空配列なら何も返さない。
     *
     * @param Builder<QaThread> $query
     * @param array<int, string> $certificationIds
     *
     * @return Builder<QaThread>
     */
    public function scopeForCertifications(Builder $query, array $certificationIds): Builder
    {
        if ($certificationIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('certification_id', $certificationIds);
    }

    /**
     * 本文・タイトルの部分一致検索。null / 空文字なら絞り込まない。
     *
     * @param Builder<QaThread> $query
     *
     * @return Builder<QaThread>
     */
    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = $keyword !== null ? trim($keyword) : '';

        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('title', 'like', '%'.$keyword.'%')
                ->orWhere('body', 'like', '%'.$keyword.'%');
        });
    }
}
