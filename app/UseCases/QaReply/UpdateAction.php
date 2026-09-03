<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;

/**
 * 回答の編集ユースケース。本文のみ更新する。
 *
 * @see QaReplyController::update()
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        $reply->update([
            'body' => $validated['body'],
        ]);

        return $reply;
    }
}
