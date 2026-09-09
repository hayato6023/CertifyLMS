<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\DailyLimitExceededException;
use App\Exceptions\AiChat\GeminiException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\SendMessageAction;
use App\UseCases\AiChat\StoreConversationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * AI 相談 Controller(学習中受講生のみ、機能スイッチはルートで担保)。
 *
 * 会話の一覧(最新へ誘導)/ 作成 / 詳細 / タイトル編集 / 削除と、メッセージ送信(JSON 同期応答)。
 * 会話はオーナー本人のみ操作可(AiChatConversationPolicy)。
 */
final class AiChatController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $latest = auth()->user()->aiChatConversations()->latest('last_message_at')->first();

        if ($latest !== null) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    public function store(StoreConversationRequest $request, StoreConversationAction $action): RedirectResponse
    {
        $conversation = $action($request->user(), $request->validated());

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(AiChatConversation $conversation): View
    {
        $this->authorize('view', $conversation);

        return view('ai-chat.show', [
            'conversation' => $conversation->load(['messages', 'section', 'enrollment.certification']),
        ]);
    }

    public function update(AiChatConversation $conversation, UpdateConversationRequest $request): RedirectResponse
    {
        $this->authorize('update', $conversation);

        // 手動編集した会話は以後 AI による自動タイトル更新を止める
        $conversation->update([
            'title' => $request->validated()['title'],
            'auto_title' => false,
        ]);

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function destroy(AiChatConversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return redirect()->route('ai-chat.index')->with('success', '会話を削除しました。');
    }

    public function storeMessage(AiChatConversation $conversation, StoreMessageRequest $request, SendMessageAction $action): JsonResponse
    {
        $this->authorize('view', $conversation);

        try {
            $result = $action($conversation, $request->validated()['content']);
        } catch (DailyLimitExceededException) {
            return response()->json(['message' => '本日の利用上限に達しました。'], 429);
        } catch (GeminiException $e) {
            return response()->json([
                'message' => 'AI が応答できませんでした。',
                'upstream_status' => $e->upstreamStatus,
            ], 502);
        }

        $conversation->refresh();

        return response()->json([
            'user_message' => [
                'id' => $result['user']->id,
                'role' => 'user',
                'content' => $result['user']->content,
                'status' => $result['user']->status,
                'created_at' => $result['user']->created_at?->toIso8601String(),
            ],
            'assistant_message' => [
                'id' => $result['assistant']->id,
                'role' => 'assistant',
                'content' => $result['assistant']->content,
                'status' => $result['assistant']->status,
                'created_at' => $result['assistant']->created_at?->toIso8601String(),
            ],
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ]);
    }
}
