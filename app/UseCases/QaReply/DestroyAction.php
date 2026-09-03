<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;

/**
 * 回答の削除ユースケース(物理削除)。
 *
 * @see QaReplyController::destroy()
 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        $reply->delete();
    }
}
