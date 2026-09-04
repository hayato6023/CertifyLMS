<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\EnrollmentGoal;

/**
 * 個人目標の達成マークを解除するユースケース。achieved_at を null に戻す(冪等)。
 *
 * @see EnrollmentGoalController::unmarkAchieved()
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        $goal->update(['achieved_at' => null]);

        return $goal;
    }
}
