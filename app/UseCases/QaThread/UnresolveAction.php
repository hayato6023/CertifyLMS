<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;

/**
 * 質問スレッドを未解決に戻すユースケース。
 *
 * status=open に変更し resolved_at をクリアする。
 *
 * @see QaThreadController::unresolve()
 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);

        return $thread;
    }
}
