<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * 本人によるパスワード変更 Action。
 *
 * 現在のパスワード一致確認は FormRequest(current_password ルール)で担保する前提で、
 * 本 Action は新パスワードのハッシュ保存のみを行う。
 */
final class UpdatePasswordAction
{
    /**
     * @param array{password: string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return $user->refresh();
    }
}
