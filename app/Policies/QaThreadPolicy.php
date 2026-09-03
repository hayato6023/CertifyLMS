<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板スレッド(公開コンテキスト)の認可ポリシー。
 *
 * - view: 受講生は公開済資格すべて、コーチは担当資格のみ。公開停止中の資格は受講生 / コーチには見えない。
 * - create: 受講生のみ(スレッド投稿は受講生専用)。
 * - update / delete / resolve / unresolve: 投稿者本人のみ。
 *
 * 管理者モデレーション(公開停止資格を含む横断閲覧 / 削除)は QaThreadModerationController で
 * `role:admin` ミドルウェア + 明示 authorize により制御し、本 Policy は公開コンテキスト専用とする。
 */
class QaThreadPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Student, UserRole::Coach], true);
    }

    public function view(User $auth, QaThread $thread): bool
    {
        $thread->loadMissing('certification');
        $certification = $thread->certification;

        if ($certification === null) {
            return false;
        }

        if ($auth->role === UserRole::Coach) {
            // コーチは担当資格のスレッドのみ閲覧可
            return in_array($certification->id, $auth->coachingCertificationIds(), true);
        }

        if ($auth->role === UserRole::Student) {
            // 受講生は公開済資格のスレッドのみ閲覧可
            return $certification->status === CertificationStatus::Published;
        }

        return false;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function update(User $auth, QaThread $thread): bool
    {
        return $this->isAuthor($auth, $thread);
    }

    public function delete(User $auth, QaThread $thread): bool
    {
        return $this->isAuthor($auth, $thread);
    }

    public function resolve(User $auth, QaThread $thread): bool
    {
        return $this->isAuthor($auth, $thread);
    }

    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $this->isAuthor($auth, $thread);
    }

    private function isAuthor(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student && $thread->user_id === $auth->id;
    }
}
