<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証ダウンロードの認可。
 *
 * - 受講生: 自分の修了証のみ
 * - コーチ: 自分の担当資格に紐づく修了証のみ
 * - 管理者: すべて
 */
final class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => in_array($certificate->certification_id, $user->coachingCertificationIds(), true),
            UserRole::Student => $certificate->user_id === $user->id,
            default => false,
        };
    }
}
