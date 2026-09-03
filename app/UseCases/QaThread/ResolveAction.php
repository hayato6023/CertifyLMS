<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;

/**
 * 質問スレッドを解決済にマークするユースケース。
 *
 * status=resolved に変更し resolved_at を打刻する。既に解決済でも冪等に扱う。
 *
 * @see QaThreadController::resolve()
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => now(),
        ]);

        return $thread;
    }
}
