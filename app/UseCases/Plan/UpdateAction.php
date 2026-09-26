<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\User;

/**
 * プランの編集ユースケース。基本情報のみ更新(状態は変更しない)。
 * updated_by に操作者を記録する。
 *
 * @see PlanController::update()
 */
final class UpdateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: ?string,
     *     duration_days: int,
     *     default_meeting_quota: int,
     *     sort_order?: ?int
     * } $validated
     */
    public function __invoke(Plan $plan, User $admin, array $validated): Plan
    {
        $plan->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? $plan->sort_order,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan;
    }
}
