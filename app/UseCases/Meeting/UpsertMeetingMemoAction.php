<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Support\Facades\DB;

/**
 * 担当コーチによる面談メモ作成・更新のユースケース。
 *
 * reserved / completed の面談のみメモを残せる(canceled は不可)。ステータス判定と upsert を
 * 同一トランザクション境界に含める。
 *
 * MeetingController::upsertMemo から抽出。振る舞いは移設前と同一。
 */
final class UpsertMeetingMemoAction
{
    /**
     * @throws MeetingStatusTransitionException
     */
    public function __invoke(Meeting $meeting, string $body): MeetingMemo
    {
        return DB::transaction(function () use ($meeting, $body) {
            if (! in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true)) {
                throw MeetingStatusTransitionException::forMemo();
            }

            return MeetingMemo::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['body' => $body],
            );
        });
    }
}
