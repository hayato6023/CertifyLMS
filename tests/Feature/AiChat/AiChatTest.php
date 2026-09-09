<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI 相談(S-A-02)の検証。Gemini API はモック(Http::fake)。
 *
 * - 学習中受講生のみ利用可(コーチ / 他受講生は不可)
 * - メッセージ送信で user + assistant が保存され JSON が返る
 * - 日次上限超過は 429、Gemini 失敗は 502(user メッセージは残る)
 * - タイトル手動編集で auto_title が false になる
 * - 機能スイッチ OFF で 404
 */
class AiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai-chat.enabled', true);
        Config::set('ai-chat.gemini.api_key', 'test-key');
        Config::set('ai-chat.daily_limit', 30);
    }

    private function fakeGeminiOk(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'これは AI の回答です。']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20],
            ], 200),
        ]);
    }

    private function student(): User
    {
        return User::factory()->student()->inProgress()->create();
    }

    private function conversationFor(User $user): AiChatConversation
    {
        return AiChatConversation::factory()->create(['user_id' => $user->id]);
    }

    public function test_student_can_create_conversation_with_first_message(): void
    {
        $this->fakeGeminiOk();
        $student = $this->student();

        $this->actingAs($student)->post(route('ai-chat.conversations.store'), [
            'message' => '二分探索の計算量は?',
        ])->assertRedirect();

        $conversation = $student->aiChatConversations()->firstOrFail();
        $this->assertDatabaseHas('ai_chat_messages', ['ai_chat_conversation_id' => $conversation->id, 'role' => 'user']);
        $this->assertDatabaseHas('ai_chat_messages', ['ai_chat_conversation_id' => $conversation->id, 'role' => 'assistant', 'status' => 'completed']);
    }

    public function test_send_message_returns_json_with_assistant_reply(): void
    {
        $this->fakeGeminiOk();
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => '質問です'])
            ->assertOk()
            ->assertJsonStructure([
                'user_message' => ['id', 'role', 'content', 'status'],
                'assistant_message' => ['id', 'role', 'content', 'status'],
                'conversation' => ['id', 'title'],
            ])
            ->assertJsonPath('assistant_message.content', 'これは AI の回答です。');
    }

    public function test_message_title_auto_generated_on_first_message(): void
    {
        $this->fakeGeminiOk();
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => 'ソートアルゴリズムの安定性について']);

        $this->assertNotSame('新しい相談', $conversation->refresh()->title);
    }

    public function test_daily_limit_returns_429(): void
    {
        $this->fakeGeminiOk();
        Config::set('ai-chat.daily_limit', 2);
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        // 既に上限分の user メッセージを当日投入済みにする
        AiChatMessage::factory()->count(2)->create([
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
        ]);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => '3件目'])
            ->assertStatus(429);
    }

    public function test_gemini_failure_returns_502_and_keeps_user_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(null, 503),
        ]);
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => '失敗する質問'])
            ->assertStatus(502)
            ->assertJsonPath('upstream_status', 503);

        // user メッセージは残る(再送できる)
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '失敗する質問',
        ]);
    }

    public function test_other_student_cannot_view_conversation(): void
    {
        $owner = $this->student();
        $conversation = $this->conversationFor($owner);
        $other = $this->student();

        $this->actingAs($other)->get(route('ai-chat.conversations.show', $conversation))->assertForbidden();
    }

    public function test_coach_cannot_access_ai_chat(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('ai-chat.index'))->assertForbidden();
    }

    public function test_title_can_be_edited_and_disables_auto_title(): void
    {
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        $this->actingAs($student)->patch(route('ai-chat.conversations.update', $conversation), [
            'title' => '手動タイトル',
        ])->assertRedirect();

        $conversation->refresh();
        $this->assertSame('手動タイトル', $conversation->title);
        $this->assertFalse($conversation->auto_title);
    }

    public function test_owner_can_delete_conversation(): void
    {
        $student = $this->student();
        $conversation = $this->conversationFor($student);

        $this->actingAs($student)->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertRedirect(route('ai-chat.index'));

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_disabled_switch_returns_404(): void
    {
        Config::set('ai-chat.enabled', false);
        $student = $this->student();

        $this->actingAs($student)->get(route('ai-chat.index'))->assertNotFound();
    }
}
