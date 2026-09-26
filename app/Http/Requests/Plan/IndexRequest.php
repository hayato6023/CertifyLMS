<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Http\Controllers\PlanController;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * プラン一覧の絞り込みリクエスト。keyword / status で絞り込む。
 *
 * @see PlanController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plan::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_column(PlanStatus::cases(), 'value'))],
        ];
    }
}
