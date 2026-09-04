<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\EnrollmentGoal;

/**
 * 個人目標の編集ユースケース。タイトル / 詳細 / 目標期日を更新する(達成状態は変更しない)。
 *
 * @see EnrollmentGoalController::update()
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: ?string, target_date?: ?string} $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        $goal->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
        ]);

        return $goal;
    }
}
