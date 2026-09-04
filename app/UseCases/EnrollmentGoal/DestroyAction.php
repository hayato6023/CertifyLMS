<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\EnrollmentGoal;

/**
 * 個人目標の削除ユースケース(物理削除、履歴は残さない)。
 *
 * @see EnrollmentGoalController::destroy()
 */
final class DestroyAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        $goal->delete();
    }
}
