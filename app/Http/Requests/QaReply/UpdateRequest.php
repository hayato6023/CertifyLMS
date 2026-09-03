<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答の編集リクエスト。
 *
 * 本文のみ更新可。認可(投稿者本人のみ)は Policy の update に委譲する。
 *
 * @see QaReplyController::update()
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reply = $this->route('reply');

        return $reply instanceof QaReply
            && ($this->user()?->can('update', $reply) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '回答本文',
        ];
    }
}
