<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 質問掲示板一覧の絞り込みリクエスト。
 *
 * status は '' / unresolved / resolved の 3 値(Blade の segmented filter に対応)。
 * certification_id / keyword は任意。認可は Controller 側で viewAny を確認する。
 *
 * @see QaThreadController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', QaThread::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:unresolved,resolved'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }
}
