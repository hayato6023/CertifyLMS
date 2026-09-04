<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Http\Controllers\Admin\PlanController;
use App\Models\Plan;
use App\Models\User;

/**
 * プランを公開(draft → published)するユースケース。
 * 下書き以外からの遷移は不正で PlanInvalidTransitionException(409)。
 *
 * @see PlanController::publish()
 */
final class PublishAction
{
    /**
     * @throws PlanInvalidTransitionException 下書き以外からの呼出
     */
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanInvalidTransitionException::forPublish();
        }

        $plan->update([
            'status' => PlanStatus::Published->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $plan;
    }
}
