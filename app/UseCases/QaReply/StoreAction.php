<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 回答の新規投稿ユースケース。
 *
 * 対象スレッドに紐づく回答を INSERT する。投稿者は認証ユーザー。
 *
 * @see QaReplyController::store()
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(QaThread $thread, User $author, array $validated): QaReply
    {
        return $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]);
    }
}
