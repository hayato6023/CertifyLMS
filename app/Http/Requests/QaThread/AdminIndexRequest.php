<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Http\Controllers\Admin\QaThreadModerationController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 管理者モデレーション一覧の絞り込みリクエスト。
 *
 * 認可は `role:admin` ミドルウェアで担保するため authorize() は常に true を返す。
 * フィルタ値の検証ルールは公開側 IndexRequest と同一。
 *
 * @see QaThreadModerationController::index()
 */
class AdminIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
