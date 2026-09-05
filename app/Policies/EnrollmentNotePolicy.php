<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチメモの認可。
 *
 * - 管理者は全メモを作成 / 編集 / 削除できる
 * - コーチは担当資格に登録された受講登録にのみ作成でき、編集 / 削除は自分が作成したメモのみ
 * - 受講生は一切操作・閲覧できない
 */
final class EnrollmentNotePolicy
{
    /**
     * メモ作成。管理者は任意、コーチは担当資格に登録された受講登録に対してのみ許可。
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach
            && in_array($enrollment->certification_id, $user->coachingCertificationIds(), true);
    }

    /**
     * メモ編集。管理者は任意、コーチは作成者本人のみ。
     */
    public function update(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach && $note->user_id === $user->id;
    }

    /**
     * メモ削除。管理者は任意、コーチは作成者本人のみ。
     */
    public function delete(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach && $note->user_id === $user->id;
    }
}
