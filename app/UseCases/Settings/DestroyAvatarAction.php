<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * 本人によるアバター画像削除 Action。
 *
 * 自ディスク管理下(avatars/ 配下)のファイルであれば実体を削除し、User.avatar_url を null に戻す。
 * 外部 URL 等の管理外パスの場合はファイル削除は行わず、参照(avatar_url)のみ解除する。
 */
final class DestroyAvatarAction
{
    public function __invoke(User $user): User
    {
        $current = $user->avatar_url;

        if ($current !== null) {
            $prefix = Storage::disk('public')->url('avatars/');
            if (str_starts_with($current, $prefix)) {
                Storage::disk('public')->delete('avatars/'.basename($current));
            }
        }

        $user->update(['avatar_url' => null]);

        return $user->refresh();
    }
}
