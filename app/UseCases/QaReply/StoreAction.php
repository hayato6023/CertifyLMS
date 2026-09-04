<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Enums\UserStatus;
use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;

/**
 * 回答の新規投稿ユースケース。
 *
 * 対象スレッドに紐づく回答を INSERT し、スレッド投稿者へ通知(アプリ内＋メール)を発火する。
 * 回答者自身がスレッド投稿者の場合や、投稿者が退会済みの場合は通知しない(配信対象の制御)。
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
        $reply = $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]);

        $this->notifyThreadOwner($thread, $author, $reply);

        return $reply;
    }

    private function notifyThreadOwner(QaThread $thread, User $author, QaReply $reply): void
    {
        $owner = $thread->user;

        if ($owner === null || $owner->id === $author->id) {
            return;
        }

        if ($owner->status === UserStatus::Withdrawn) {
            return;
        }

        $owner->notify(new QaReplyReceivedNotification($reply));
    }
}
