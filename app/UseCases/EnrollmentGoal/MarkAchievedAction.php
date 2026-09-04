<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\EnrollmentGoal;

/**
 * 個人目標を達成済にマークするユースケース。achieved_at を現在時刻で打刻する(冪等)。
 *
 * @see EnrollmentGoalController::markAchieved()
 */
final class MarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        $goal->update(['achieved_at' => now()]);

        return $goal;
    }
}
