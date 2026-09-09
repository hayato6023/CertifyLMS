<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Exceptions\AiChat\DailyLimitExceededException;
use App\Exceptions\AiChat\GeminiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\GeminiService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 受講生の質問を保存し、Gemini から同期応答を取得して保存するユースケース。
 *
 * - 送信前に日次上限をチェック(超過は DailyLimitExceededException = 429)
 * - user メッセージは必ず保存する(AI 応答が失敗しても質問は残り、再送できる)
 * - 資格 / 教材の文脈を systemInstruction に自動付与する
 * - AI 応答成功時、auto_title の会話は最初の質問からタイトルを設定する
 * - AI 応答失敗時は status=error の assistant を残し、GeminiException を再送出(Controller が 502)
 */
final class SendMessageAction
{
    public function __construct(private readonly GeminiService $gemini) {}

    /**
     * @return array{user: AiChatMessage, assistant: AiChatMessage}
     *
     * @throws DailyLimitExceededException
     * @throws GeminiException
     */
    public function __invoke(AiChatConversation $conversation, string $content): array
    {
        $this->assertWithinDailyLimit($conversation);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
            'status' => 'completed',
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        try {
            $result = $this->gemini->generateReply(
                $this->buildSystemContext($conversation),
                $this->buildHistory($conversation),
            );
        } catch (GeminiException $e) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'AI が応答できませんでした。',
                'status' => 'error',
                'meta' => ['upstream_status' => $e->upstreamStatus],
            ]);

            throw $e;
        }

        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['content'],
            'status' => 'completed',
            'meta' => $result['meta'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();
        $this->maybeAutoTitle($conversation, $content);

        return ['user' => $userMessage, 'assistant' => $assistant];
    }

    private function assertWithinDailyLimit(AiChatConversation $conversation): void
    {
        $limit = (int) config('ai-chat.daily_limit');

        $sentToday = AiChatMessage::query()
            ->where('role', 'user')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $conversation->user_id))
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($sentToday >= $limit) {
            throw new DailyLimitExceededException;
        }
    }

    private function buildSystemContext(AiChatConversation $conversation): string
    {
        $lines = ['あなたは学習者を支援する日本語の学習アシスタントです。簡潔で正確に回答してください。'];

        $conversation->loadMissing(['section', 'enrollment.certification']);
        if ($conversation->section?->title) {
            $lines[] = '相談者が今読んでいる教材: '.$conversation->section->title;
        } elseif ($conversation->enrollment?->certification?->name) {
            $lines[] = '相談者が目指している資格: '.$conversation->enrollment->certification->name;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{role: string, text: string}>
     */
    private function buildHistory(AiChatConversation $conversation): array
    {
        $limit = (int) config('ai-chat.history_limit');

        return $conversation->messages()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (AiChatMessage $m) => [
                'role' => $m->role === 'assistant' ? 'model' : 'user',
                'text' => $m->content,
            ])
            ->values()
            ->all();
    }

    private function maybeAutoTitle(AiChatConversation $conversation, string $firstContent): void
    {
        if (! $conversation->auto_title) {
            return;
        }
        if ($conversation->title !== '新しい相談') {
            return;
        }

        $conversation->forceFill(['title' => Str::limit(trim($firstContent), 30)])->save();
    }
}
