<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI 相談機能の ON/OFF スイッチ。config('ai-chat.enabled') が false の環境では
 * 関連ルートを 404 にして機能ごと無効化する。
 */
final class EnsureAiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('ai-chat.enabled'), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
