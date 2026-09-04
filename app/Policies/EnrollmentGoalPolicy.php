<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標(EnrollmentGoal)の認可ポリシー。
 *
 * - create / update / delete / markAchieved / unmarkAchieved: 受講中(learning)の受講生本人のみ。
 * - コーチ / 管理者 / 他受講生は操作できない(閲覧は受講登録詳細画面側の EnrollmentPolicy::view で担保)。
 *
 * create は対象 Enrollment を第 2 引数として渡して判定する。
 */
class EnrollmentGoalPolicy
{
    public function create(User $auth, Enrollment $enrollment): bool
    {
        return $this->isOwnerLearning($auth, $enrollment);
    }

    public function update(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerLearning($auth, $goal->enrollment);
    }

    public function delete(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerLearning($auth, $goal->enrollment);
    }

    public function markAchieved(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerLearning($auth, $goal->enrollment);
    }

    public function unmarkAchieved(User $auth, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerLearning($auth, $goal->enrollment);
    }

    private function isOwnerLearning(User $auth, Enrollment $enrollment): bool
    {
        return $auth->role === UserRole::Student
            && $enrollment->user_id === $auth->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }
}
