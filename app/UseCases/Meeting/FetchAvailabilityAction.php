<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 予約画面向けの空き枠取得ユースケース。
 *
 * Enrollment に紐づく資格の指定日について、MeetingAvailabilityService から空き枠一覧を取得する。
 * JSON レスポンスへの整形は Controller 側のレスポンス整形として残し、本 Action はデータ取得に徹する。
 *
 * MeetingController::fetchAvailability から抽出。取得ロジックは移設前と同一。
 *
 * @phpstan-return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
 */
final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    public function __invoke(Enrollment $enrollment, Carbon $date): Collection
    {
        return $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );
    }
}
