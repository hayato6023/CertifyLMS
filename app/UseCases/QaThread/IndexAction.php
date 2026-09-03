<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Http\Controllers\QaThreadController;
use App\Models\QaThread;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問スレッド一覧を取得するユースケース。
 *
 * 閲覧可能な資格 ID 群($certificationIds)でスコープし、status / keyword / certification_id で絞り込む。
 * status は 'unresolved' / 'resolved' の文字列(Blade の segmented filter 値)。
 * N+1 回避のため user / certification を eager load し、replies_count を withCount で供給する。
 *
 * @see QaThreadController::index()
 */
final class IndexAction
{
    /**
     * @param array<int, string> $certificationIds 閲覧可能な資格 ID 群(空なら 0 件)
     */
    public function __invoke(
        array $certificationIds,
        ?string $status,
        ?string $certificationId,
        ?string $keyword,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $statusEnum = match ($status) {
            'unresolved' => QaThreadStatus::Open,
            'resolved' => QaThreadStatus::Resolved,
            default => null,
        };

        return QaThread::query()
            ->forCertifications($certificationIds)
            ->when($certificationId, fn ($q) => $q->where('certification_id', $certificationId))
            ->when($statusEnum, fn ($q) => $q->where('status', $statusEnum->value))
            ->keyword($keyword)
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
