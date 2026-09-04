<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreAvatarRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\UseCases\Settings\DestroyAvatarAction;
use App\UseCases\Settings\StoreAvatarAction;
use App\UseCases\Settings\UpdatePasswordAction;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 設定・プロフィール Controller(全ロール共通、本人のみ)。
 *
 * プロフィール表示 / 編集・パスワード変更・アバター画像の登録 / 削除を提供する。
 * 認証必須だがロール制約は無く、修了済(graduated)受講生も利用できる
 * (プラン機能ではないため active-learning ミドルウェアの対象外)。
 */
final class SettingsController extends Controller
{
    public function profile(): View
    {
        return view('settings.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile')
            ->with('success', 'プロフィールを更新しました。');
    }

    public function updatePassword(UpdatePasswordRequest $request, UpdatePasswordAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }

    public function storeAvatar(StoreAvatarRequest $request, StoreAvatarAction $action): RedirectResponse
    {
        $action($request->user(), $request->file('avatar'));

        return redirect()
            ->route('settings.profile')
            ->with('success', 'アイコン画像を更新しました。');
    }

    public function destroyAvatar(DestroyAvatarAction $action): RedirectResponse
    {
        $action(auth()->user());

        return redirect()
            ->route('settings.profile')
            ->with('success', 'アイコン画像を削除しました。');
    }
}
