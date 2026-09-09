<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Exceptions\AiChat\DailyLimitExceededException;
use App\Exceptions\AiChat\GeminiException;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;

/**
 * AI 相談の会話を新規作成するユースケース。
 *
 * 教材(section)から開始した場合は、同じ教材の会話が乱立しないよう既存の会話を再利用する。
 * 初回メッセージ(任意)がある場合は続けて AI 応答を取得する(失敗しても会話と質問は残す)。
 */
final class StoreConversationAction
{
    public function __construct(private readonly SendMessageAction $sendMessage) {}

    /**
     * @param array{message?: ?string, section_id?: ?string, enrollment_id?: ?string} $input
     */
    public function __invoke(User $student, array $input): AiChatConversation
    {
        $sectionId = $input['section_id'] ?? null;
        $enrollmentId = $this->resolveEnrollmentId($student, $input, $sectionId);

        // 教材起点の会話は乱立を防ぐため既存を再利用
        $conversation = null;
        if ($sectionId !== null) {
            $conversation = $student->aiChatConversations()
                ->where('section_id', $sectionId)
                ->latest('last_message_at')
                ->first();
        }

        $conversation ??= $student->aiChatConversations()->create([
            'section_id' => $sectionId,
            'enrollment_id' => $enrollmentId,
            'title' => '新しい相談',
            'auto_title' => true,
            'last_message_at' => now(),
        ]);

        $message = trim((string) ($input['message'] ?? ''));
        if ($message !== '') {
            try {
                ($this->sendMessage)($conversation, $message);
            } catch (GeminiException|DailyLimitExceededException) {
                // 会話と user メッセージは残す。詳細画面で再送 / 上限案内を扱う。
            }
        }

        return $conversation;
    }

    private function resolveEnrollmentId(User $student, array $input, ?string $sectionId): ?string
    {
        if (! empty($input['enrollment_id'])) {
            return $input['enrollment_id'];
        }

        if ($sectionId !== null) {
            $section = Section::query()->with('chapter.part.certification')->find($sectionId);
            $certificationId = $section?->chapter?->part?->certification_id;
            if ($certificationId !== null) {
                return Enrollment::query()
                    ->where('user_id', $student->id)
                    ->where('certification_id', $certificationId)
                    ->value('id');
            }
        }

        return null;
    }
}
