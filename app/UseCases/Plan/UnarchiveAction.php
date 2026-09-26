<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\User;

/**
 * プランを下書きへ戻す(archived → draft)ユースケース。
 * アーカイブ以外からの遷移は不正で PlanInvalidTransitionException(409)。
 *
 * @see PlanController::unarchive()
 */
final class UnarchiveAction
{
    /**
     * @throws PlanInvalidTransitionException アーカイブ以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Archived) {
            throw PlanInvalidTransitionException::forUnarchive();
        }

        $plan->update([
            'status' => PlanStatus::Draft->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan;
    }
}
