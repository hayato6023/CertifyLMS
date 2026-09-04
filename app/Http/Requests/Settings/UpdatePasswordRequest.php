<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * パスワード変更リクエスト。
 *
 * 現在のパスワード一致(current_password)を確認したうえで、新パスワードは 8 文字以上 + confirmed。
 * エラーは updatePassword 名前付きバッグに載せ、Blade のパスワードタブで表示する。
 */
final class UpdatePasswordRequest extends FormRequest
{
    /**
     * バリデーションエラーを格納する名前付きエラーバッグ。
     * Blade のパスワードタブは updatePassword バッグを参照する。
     *
     * @var string
     */
    protected $errorBag = 'updatePassword';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => '現在のパスワード',
            'password' => '新しいパスワード',
        ];
    }
}
