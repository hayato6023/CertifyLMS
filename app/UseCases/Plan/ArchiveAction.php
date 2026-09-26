<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\User;

/**
 * プランをアーカイブ(published → archived)するユースケース。
 * 公開中以外からの遷移は不正で PlanInvalidTransitionException(409)。
 * アーカイブ後は招待 / プラン延長の選択肢から外れるが、受講中ユーザーの参照は維持される。
 *
 * @see PlanController::archive()
 */
final class ArchiveAction
{
    /**
     * @throws PlanInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Published) {
            throw PlanInvalidTransitionException::forArchive();
        }

        $plan->update([
            'status' => PlanStatus::Archived->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan;
    }
}
