<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Http\Controllers\Admin\PlanController;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * プラン一覧を取得するユースケース。keyword(プラン名部分一致) / status で絞り込み、
 * sort_order 昇順 + 作成日時降順で paginate する。
 *
 * 契約中の受講者数(users_count)を withCount で供給する。
 *
 * @see PlanController::index()
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?string $status,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $statusEnum = $status !== null && $status !== ''
            ? PlanStatus::from($status)
            : null;

        return Plan::query()
            ->when($keyword !== null && $keyword !== '', fn ($q) => $q->where('name', 'like', '%'.$keyword.'%'))
            ->when($statusEnum, fn ($q) => $q->where('status', $statusEnum->value))
            ->withCount('users')
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
