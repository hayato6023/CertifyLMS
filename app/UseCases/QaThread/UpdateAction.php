<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;

/**
 * 質問スレッドの編集ユースケース。
 *
 * タイトル・本文のみ更新する(資格・状態は変更しない)。
 *
 * @see QaThreadController::update()
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        $thread->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return $thread;
    }
}
