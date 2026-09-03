<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Http\Controllers\Admin\MeetingPackController;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックをアーカイブ(published → archived)するユースケース。
 * 公開中以外からの遷移は不正で MeetingPackInvalidTransitionException(409)。
 *
 * @see MeetingPackController::archive()
 */
final class ArchiveAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(MeetingPack $pack, User $admin): MeetingPack
    {
        if ($pack->status !== MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forArchive();
        }

        $pack->update([
            'status' => MeetingPackStatus::Archived->value,
            'updated_by_user_id' => $admin->id,
        ]);

        return $pack;
    }
}
