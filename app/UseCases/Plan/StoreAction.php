<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\User;

/**
 * プランの新規作成ユースケース。初期状態は下書き(draft)固定。
 * created_by / updated_by に操作者を記録する。
 *
 * @see PlanController::store()
 */
final class StoreAction
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
    public function __invoke(User $admin, array $validated): Plan
    {
        return Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }
}
