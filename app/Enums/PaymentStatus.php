<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 追加面談購入の決済状態。
 *
 * - Pending: Checkout 開始後、決済完了通知待ち
 * - Completed: 決済完了(残数加算済み)
 * - Failed: 決済失敗 / 中断(残数は変わらない)
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '保留',
            self::Completed => '完了',
            self::Failed => '失敗',
        };
    }
}
