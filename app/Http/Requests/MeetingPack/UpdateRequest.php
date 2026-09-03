<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Http\Controllers\Admin\MeetingPackController;
use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 面談パックの編集リクエスト。基本情報のみ更新(状態遷移はこのフォームでは扱わない)。
 *
 * @see MeetingPackController::update()
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pack = $this->route('plan');

        return $pack instanceof MeetingPack
            && ($this->user()?->can('update', $pack) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'SKU 名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => 'Stripe Price ID',
            'sort_order' => '並び順',
        ];
    }
}
