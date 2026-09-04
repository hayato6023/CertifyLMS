<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentGoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講登録(Enrollment)配下の個人学習目標。受講生本人が CRUD する。
 *
 * achieved_at が null なら未達成、値があれば達成済(その時刻)。
 * 関連: Enrollment(BelongsTo)
 */
class EnrollmentGoal extends Model
{
    /** @use HasFactory<EnrollmentGoalFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'enrollment_id',
        'title',
        'description',
        'target_date',
        'achieved_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 一覧の表示順。未達成を先頭にし、その中で目標期日が近い順(期日未設定は末尾)、
     * 同条件は新しく作成した順に並べる。
     *
     * @param Builder<EnrollmentGoal> $query
     *
     * @return Builder<EnrollmentGoal>
     */
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('achieved_at IS NOT NULL')       // 未達成(NULL)を先頭
            ->orderByRaw('target_date IS NULL')            // 期日未設定を末尾
            ->orderBy('target_date')                       // 期日が近い順
            ->latest();                                    // 同条件は新しい順
    }
}
