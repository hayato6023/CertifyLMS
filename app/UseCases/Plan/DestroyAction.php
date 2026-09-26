<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanDeletionBlockedException;
use App\Http\Controllers\PlanController;
use App\Models\Plan;

/**
 * プランの削除ユースケース(物理削除)。
 *
 * 下書き かつ 受講者が紐づいていないプランのみ削除できる(参照整合性を守る)。
 * 認可は Policy::delete でも担保するが、UseCase でも二重に防御する(受講者ありは 409)。
 *
 * @see PlanController::destroy()
 */
final class DestroyAction
{
    /**
     * @throws PlanDeletionBlockedException 下書き以外 or 受講者が紐づく場合
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft || $plan->users()->count() > 0) {
            throw PlanDeletionBlockedException::make();
        }

        $plan->delete();
    }
}
