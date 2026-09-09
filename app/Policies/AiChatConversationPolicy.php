<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI 相談の会話の認可。学習中の受講生でオーナー本人のみ操作できる。
 *
 * (機能全体の ON/OFF とロール・受講状態のグループ制御はルート側のミドルウェアで担保し、
 *  ここでは「会話オーナー本人」を確認する)
 */
final class AiChatConversationPolicy
{
    private function isActiveStudentOwner(User $user, AiChatConversation $conversation): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $conversation->user_id === $user->id;
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $this->isActiveStudentOwner($user, $conversation);
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->isActiveStudentOwner($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->isActiveStudentOwner($user, $conversation);
    }
}
