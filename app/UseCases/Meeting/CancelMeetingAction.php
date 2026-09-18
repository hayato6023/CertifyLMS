<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

/**
 * 当事者(受講生 or コーチ)による面談キャンセルのユースケース。
 *
 * reserved かつ開始前のみキャンセル可。対象行を lockForUpdate() で取得し直してステータスを再確認し、
 * canceled へ遷移させたうえで消費済の面談回数 1 回分を返却する(RefundQuota)。ステータス判定・
 * 回数返却を同一トランザクション境界に含める。
 *
 * MeetingController::cancel から抽出。認可(cancel Policy)は Controller 側に残す。振る舞いは移設前と同一。
 */
final class CancelMeetingAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
    ) {}

    /**
     * @throws MeetingStatusTransitionException
     * @throws MeetingAlreadyStartedException
     */
    public function __invoke(Meeting $meeting, User $actor): void
    {
        DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);
        });
    }
}
