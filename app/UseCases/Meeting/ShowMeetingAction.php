<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;

/**
 * 面談詳細の関連ロードユースケース。
 *
 * 詳細画面が必要とする関連(enrollment.certification / coach / student / canceledBy / meetingMemo)を
 * まとめて Eager Load して返す。認可(view Policy)は Controller 側に残す。
 *
 * MeetingController::show から抽出。ロード対象は移設前と同一。
 */
final class ShowMeetingAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);
    }
}
