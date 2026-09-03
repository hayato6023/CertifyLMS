<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答(QaReply)の認可ポリシー。
 *
 * - create: 受講生(公開済資格) / コーチ(担当資格)のみ。管理者は回答不可。
 * - update / delete: 投稿者本人のみ。
 *
 * create は Blade / FormRequest から `can('create', [QaReply::class, $thread])` の形で
 * 対象スレッドを第 2 引数として渡して呼ばれる。
 */
class QaReplyPolicy
{
    public function create(User $auth, QaThread $thread): bool
    {
        $thread->loadMissing('certification');
        $certification = $thread->certification;

        if ($certification === null) {
            return false;
        }

        if ($auth->role === UserRole::Coach) {
            // コーチは担当資格のスレッドにのみ回答できる
            return in_array($certification->id, $auth->coachingCertificationIds(), true);
        }

        if ($auth->role === UserRole::Student) {
            // 受講生は公開済資格のスレッドに回答できる
            return $certification->status === CertificationStatus::Published;
        }

        // 管理者は回答不可
        return false;
    }

    public function update(User $auth, QaReply $reply): bool
    {
        return $this->isAuthor($auth, $reply);
    }

    public function delete(User $auth, QaReply $reply): bool
    {
        return $this->isAuthor($auth, $reply);
    }

    private function isAuthor(User $auth, QaReply $reply): bool
    {
        return in_array($auth->role, [UserRole::Student, UserRole::Coach], true)
            && $reply->user_id === $auth->id;
    }
}
