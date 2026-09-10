<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

/**
 * 受講生 1 人あたりの 1 日の AI 相談送信回数上限に達した場合の例外(HTTP 429 相当)。
 */
final class DailyLimitExceededException extends RuntimeException {}
