<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * アバター画像アップロードリクエスト。
 *
 * PNG / JPEG / WebP、2MB(2048KB)以内。画像必須。
 */
final class StoreAvatarRequest extends FormRequest
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
        return [
            'avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }
}
