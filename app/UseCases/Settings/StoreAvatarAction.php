<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 本人によるアバター画像アップロード Action。
 *
 * public ディスクの avatars/ 配下に保存し、公開 URL を User.avatar_url に格納する。
 * 既存のアバターが自ディスク管理下(avatars/ 配下)にある場合は差し替え時に旧ファイルを削除する。
 */
final class StoreAvatarAction
{
    public function __invoke(User $user, UploadedFile $file): User
    {
        $this->deleteExistingManagedAvatar($user);

        $path = $file->store('avatars', 'public');

        $user->update([
            'avatar_url' => Storage::disk('public')->url($path),
        ]);

        return $user->refresh();
    }

    private function deleteExistingManagedAvatar(User $user): void
    {
        $current = $user->avatar_url;
        if ($current === null) {
            return;
        }

        $prefix = Storage::disk('public')->url('avatars/');
        if (str_starts_with($current, $prefix)) {
            $relative = 'avatars/'.basename($current);
            Storage::disk('public')->delete($relative);
        }
    }
}
