<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Http\Controllers\Admin\MeetingPackController;
use App\Models\MeetingPack;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 面談パック一覧を取得するユースケース。keyword(SKU 名部分一致) / status で絞り込み、
 * sort_order 昇順 + 作成日時降順で paginate する。
 *
 * Payment モデルが存在する環境でのみ payments_count を withCount で供給する
 * (Blade は `$plan->payments_count ?? 0` で防御している)。
 *
 * @see MeetingPackController::index()
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?string $status,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $statusEnum = $status !== null && $status !== ''
            ? MeetingPackStatus::from($status)
            : null;

        $query = MeetingPack::query()
            ->when($keyword !== null && $keyword !== '', fn ($q) => $q->where('name', 'like', '%'.$keyword.'%'))
            ->when($statusEnum, fn ($q) => $q->where('status', $statusEnum->value))
            ->ordered();

        if (class_exists(Payment::class)) {
            $query->withCount('payments');
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
