<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

/**
 * Gemini API 呼び出しの失敗。upstreamStatus に外部 API の HTTP ステータスを保持する
 * (フロントの再試行案内の出し分けに使う。API キー未設定などは null)。
 */
final class GeminiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $upstreamStatus = null,
    ) {
        parent::__construct($message);
    }
}
