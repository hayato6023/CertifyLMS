<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * お知らせ配信リクエスト。認可はルートの role:admin ミドルウェアで担保。
 *
 * 配信対象タイプに応じて対象 ID を条件付き必須にする
 * (資格指定 → target_certification_id、ユーザー指定 → target_user_id)。
 */
final class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', new Enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                Rule::requiredIf($this->input('target_type') === AnnouncementTargetType::Certification->value),
                'nullable',
                'string',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                Rule::requiredIf($this->input('target_type') === AnnouncementTargetType::User->value),
                'nullable',
                'string',
                'exists:users,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
