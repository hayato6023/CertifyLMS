<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問スレッドの新規投稿ユースケース。
 *
 * status=open 固定で INSERT する。投稿者は認証ユーザー。
 *
 * @see QaThreadController::store()
 */
final class StoreAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $validated
     */
    public function __invoke(User $author, array $validated): QaThread
    {
        return QaThread::create([
            'certification_id' => $validated['certification_id'],
            'user_id' => $author->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }
}
