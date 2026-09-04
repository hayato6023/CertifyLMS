<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の編集リクエスト。タイトル / 詳細 / 目標期日を更新する。
 *
 * 認可(受講中の受講生本人のみ)は Policy の update に委譲する。
 *
 * @see EnrollmentGoalController::update()
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof EnrollmentGoal
            && ($this->user()?->can('update', $goal) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'description' => '詳細',
            'target_date' => '目標期日',
        ];
    }
}
