<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiException;
use Illuminate\Support\Facades\Http;

/**
 * Gemini (Google Generative Language API) との同期連携サービス。
 *
 * 外部通信は Laravel HTTP クライアント経由で、テストは Http::fake() でモックする。
 * API キー未設定・API 失敗は GeminiException として投げ、呼び出し元が status=error として扱う。
 */
final class GeminiService
{
    /**
     * @param array<int, array{role: string, text: string}> $history 直近の会話履歴(role=user|model)
     *
     * @return array{content: string, meta: array<string, mixed>}
     *
     * @throws GeminiException
     */
    public function generateReply(string $systemContext, array $history): array
    {
        $apiKey = (string) config('ai-chat.gemini.api_key');
        if ($apiKey === '') {
            throw new GeminiException('Gemini API キーが未設定です。', null);
        }

        $model = (string) config('ai-chat.gemini.model');
        $endpoint = rtrim((string) config('ai-chat.gemini.endpoint'), '/');
        $url = "{$endpoint}/models/{$model}:generateContent";

        $contents = array_map(fn ($m) => [
            'role' => $m['role'],
            'parts' => [['text' => $m['text']]],
        ], $history);

        $startedAt = microtime(true);

        try {
            $response = Http::withQueryParameters(['key' => $apiKey])
                ->asJson()
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemContext]]],
                    'contents' => $contents,
                ]);
        } catch (\Throwable $e) {
            throw new GeminiException('Gemini API への接続に失敗しました。', null);
        }

        if ($response->failed()) {
            throw new GeminiException('Gemini API がエラーを返しました。', $response->status());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text) || $text === '') {
            throw new GeminiException('Gemini API から有効な応答が得られませんでした。', $response->status());
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'content' => $text,
            'meta' => [
                'model' => $model,
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
                'latency_ms' => $latencyMs,
            ],
        ];
    }
}
