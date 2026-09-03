<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;

/**
 * 質問スレッドの削除ユースケース。
 *
 * 配下の回答は DB の cascade(qa_replies.qa_thread_id)で物理削除される。
 *
 * @see QaThreadController::destroy()
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        $thread->delete();
    }
}
