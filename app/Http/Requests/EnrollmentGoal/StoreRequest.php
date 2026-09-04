<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Http\Controllers\EnrollmentGoalController;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の新規作成リクエスト。受講登録詳細画面のフォームから送信される。
 *
 * 認可(受講中の受講生本人のみ)は Policy の create に委譲する(対象 Enrollment を第 2 引数で渡す)。
 *
 * @see EnrollmentGoalController::store()
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment instanceof Enrollment
            && ($this->user()?->can('create', [EnrollmentGoal::class, $enrollment]) ?? false);
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
