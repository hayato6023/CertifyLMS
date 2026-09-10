<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * AI 相談の初期データ。固定の受講生に複数の会話を投入する
 * (過去相談の再開・履歴表示・エラー状態の再送確認用)。
 */
class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        if ($student === null) {
            return;
        }

        // 通常会話(user + assistant)
        $c1 = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'title' => '二分探索木の計算量について',
            'auto_title' => false,
            'last_message_at' => now()->subHours(2),
        ]);
        AiChatMessage::factory()->create(['ai_chat_conversation_id' => $c1->id, 'role' => 'user', 'content' => 'O(log n) になる理由を教えてください。']);
        AiChatMessage::factory()->assistant()->create(['ai_chat_conversation_id' => $c1->id, 'content' => '平衡している場合、木の高さが log n に比例するためです。']);

        // エラー状態を含む会話(再送確認用)
        $c2 = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'title' => '正規化について',
            'auto_title' => false,
            'last_message_at' => now()->subDay(),
        ]);
        AiChatMessage::factory()->create(['ai_chat_conversation_id' => $c2->id, 'role' => 'user', 'content' => '第3正規形とは?']);
        AiChatMessage::factory()->error()->create(['ai_chat_conversation_id' => $c2->id, 'content' => 'AI が応答できませんでした。', 'meta' => ['upstream_status' => 503]]);
    }
}
