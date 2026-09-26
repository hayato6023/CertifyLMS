<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Http\Controllers\MeetingPackController;
use App\Models\MeetingPack;

/**
 * 面談パックの削除ユースケース(物理削除)。
 *
 * 公開中(published)の面談パックは削除できない(購入履歴の整合性を守る)。
 * 認可(admin かつ非公開)は Policy::delete でも担保するが、UseCase でも二重に防御する。
 *
 * @see MeetingPackController::destroy()
 */
final class DestroyAction
{
    /**
     * @throws MeetingPackInvalidTransitionException 公開中の削除
     */
    public function __invoke(MeetingPack $pack): void
    {
        if ($pack->status === MeetingPackStatus::Published) {
            throw MeetingPackInvalidTransitionException::forArchive();
        }

        $pack->delete();
    }
}
