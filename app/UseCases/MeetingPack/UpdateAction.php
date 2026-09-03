<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Http\Controllers\Admin\MeetingPackController;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パックの編集ユースケース。基本情報のみ更新(状態は変更しない)。
 * updated_by に操作者を記録する。
 *
 * @see MeetingPackController::update()
 */
final class UpdateAction
{
    /**
     * @param array{
     *     name: string,
     *     description?: ?string,
     *     meeting_count: int,
     *     price: int,
     *     stripe_price_id?: ?string,
     *     sort_order?: ?int
     * } $validated
     */
    public function __invoke(MeetingPack $pack, User $admin, array $validated): MeetingPack
    {
        $pack->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $pack->sort_order,
            'updated_by_user_id' => $admin->id,
        ]);

        return $pack;
    }
}
