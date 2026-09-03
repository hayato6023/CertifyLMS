<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問スレッドの編集リクエスト。
 *
 * タイトル・本文のみ更新可(資格は変更不可)。認可(投稿者本人のみ)は Policy の update に委譲する。
 *
 * @see QaThreadController::update()
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && ($this->user()?->can('update', $thread) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
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
        ];
    }
}
