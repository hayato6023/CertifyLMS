<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談一覧取得ユースケース。
 *
 * filter (upcoming/past/all) に応じて履歴を切り替える。関連(enrollment.certification / coach)を
 * Eager Load し、scheduled_at 降順でページネートする。
 *
 * MeetingController::index から抽出。クエリ組み立ては移設前と同一。
 */
final class ListStudentMeetingsAction
{
    /**
     * @return LengthAwarePaginator<Meeting>
     */
    public function __invoke(User $student, string $filter = 'upcoming'): LengthAwarePaginator
    {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($student)
            ->orderByDesc('scheduled_at');

        return match ($filter) {
            'past' => $query->past()->paginate(20),
            'all' => $query->paginate(20),
            default => $query->upcoming()->paginate(20),
        };
    }
}
