<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 受講者が紐づく、または下書き以外のプランを削除しようとした際の例外(HTTP 409)。
 *
 * 参照整合性(受講中ユーザーの plan_id 参照 / プラン履歴)を守るためのガード。
 */
final class PlanDeletionBlockedException extends ConflictHttpException
{
    public static function make(): self
    {
        return new self('下書きかつ受講者が紐づいていないプランのみ削除できます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
