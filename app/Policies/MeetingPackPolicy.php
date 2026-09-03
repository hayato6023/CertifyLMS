<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MeetingPackStatus;
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パック(追加購入用 SKU)マスタ管理の認可ポリシー。
 *
 * 全操作は管理者のみ。受講生 / コーチはアクセス拒否。
 * 削除は公開中(published)以外のみ許可し、購入履歴の整合性を守る。
 */
class MeetingPackPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, MeetingPack $pack): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, MeetingPack $pack): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, MeetingPack $pack): bool
    {
        // 公開中の面談パックは削除不可(購入履歴の整合性を守る)
        return $auth->role === UserRole::Admin
            && $pack->status !== MeetingPackStatus::Published;
    }

    public function publish(User $auth, MeetingPack $pack): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, MeetingPack $pack): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, MeetingPack $pack): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
