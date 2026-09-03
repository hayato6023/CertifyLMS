<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Http\Controllers\QaReplyController;
use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 回答の新規投稿リクエスト。
 *
 * 認可(受講生 = 公開資格 / コーチ = 担当資格、管理者不可)は Policy の create に委譲する。
 * create は対象スレッドを第 2 引数として渡して判定する。
 *
 * @see QaReplyController::store()
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
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
