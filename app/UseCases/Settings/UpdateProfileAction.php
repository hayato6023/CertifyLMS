<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;

/**
 * 本人によるプロフィール(氏名 / 自己紹介 / コーチの固定面談 URL)更新 Action。
 *
 * メール・ロール・ステータスは本画面からは変更しない(管理者経由)。
 * meeting_url はコーチのみ更新対象とし、それ以外のロールでは受け付けない
 * (FormRequest 側でもコーチ以外の meeting_url は無視される想定だが、二重に防御する)。
 */
final class UpdateProfileAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $attrs = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach && array_key_exists('meeting_url', $validated)) {
            $attrs['meeting_url'] = $validated['meeting_url'] ?: null;
        }

        $user->update($attrs);

        return $user->refresh();
    }
}
