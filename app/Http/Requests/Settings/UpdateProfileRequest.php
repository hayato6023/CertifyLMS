<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール更新リクエスト。
 *
 * 認可は認証済みユーザー自身の更新のみ(ルートに本人以外は現れない)。
 * name / bio を受け付け、meeting_url はコーチのみ検証対象とする。email は受け付けない。
 */
final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            $rules['meeting_url'] = ['nullable', 'url', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
