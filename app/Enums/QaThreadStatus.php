<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 質問掲示板スレッドの解決状態。
 *
 * - Open: 未解決（投稿直後の既定状態）
 * - Resolved: 投稿者本人が解決済にマークした状態
 */
enum QaThreadStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => '未解決',
            self::Resolved => '解決済',
        };
    }
}
