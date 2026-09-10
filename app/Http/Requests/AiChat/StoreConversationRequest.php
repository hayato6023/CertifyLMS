<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 相談の会話作成リクエスト。認可はルート(role:student + active-learning + 機能スイッチ)で担保。
 */
final class StoreConversationRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:2000'],
            'section_id' => ['nullable', 'string', 'exists:sections,id'],
            'enrollment_id' => ['nullable', 'string', 'exists:enrollments,id'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }
}
