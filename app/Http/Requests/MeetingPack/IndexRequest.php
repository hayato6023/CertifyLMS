<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Http\Controllers\MeetingPackController;
use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 面談パック一覧の絞り込みリクエスト。keyword / status で絞り込む。
 *
 * @see MeetingPackController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MeetingPack::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_column(MeetingPackStatus::cases(), 'value'))],
        ];
    }
}
