<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Http\Controllers\MeetingPackController;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックを公開(draft → published)するユースケース。
 * 下書き以外からの遷移は不正で MeetingPackInvalidTransitionException(409)。
 *
 * @see MeetingPackController::publish()
 */
final class PublishAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 下書き以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        if ($pack->status !== MeetingPackStatus::Draft) {
            throw MeetingPackInvalidTransitionException::forPublish();
        }

        $pack->update([
            'status' => MeetingPackStatus::Published->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $pack;
    }
}
