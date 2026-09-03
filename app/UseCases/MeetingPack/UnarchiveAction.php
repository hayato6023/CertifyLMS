<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Http\Controllers\Admin\MeetingPackController;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックを下書きへ戻す(archived → draft)ユースケース。
 * アーカイブ以外からの遷移は不正で MeetingPackInvalidTransitionException(409)。
 *
 * @see MeetingPackController::unarchive()
 */
final class UnarchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException アーカイブ以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        if ($pack->status !== MeetingPackStatus::Archived) {
            throw MeetingPackInvalidTransitionException::forUnarchive();
        }

        $pack->update([
            'status' => MeetingPackStatus::Draft->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $pack;
    }
}
