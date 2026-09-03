<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingPack\IndexRequest;
use App\Http\Requests\MeetingPack\StoreRequest;
use App\Http\Requests\MeetingPack\UpdateRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Policies\MeetingPackPolicy;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\IndexAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\StoreAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use App\UseCases\MeetingPack\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者用の面談パック(追加購入用 SKU)マスタ管理 Controller。
 *
 * CRUD と状態遷移(publish / archive / unarchive)を提供する。全操作は管理者のみ
 * (`role:admin` ミドルウェア + MeetingPackPolicy)。ルートパラメータ名は Blade に合わせ `{plan}`。
 *
 * @see MeetingPackPolicy
 */
class MeetingPackController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: $validated['status'] ?? null,
        );

        return view('meeting-pack.management.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $pack = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $pack)
            ->with('success', '面談パックを作成しました。');
    }

    public function show(MeetingPack $plan): View
    {
        $this->authorize('view', $plan);

        $plan->loadMissing(['createdBy', 'updatedBy']);

        // Payment モデルが存在する環境でのみ購入履歴を eager load する
        if (class_exists(Payment::class)) {
            $plan->load(['payments' => fn ($q) => $q->with('user')->latest('paid_at')->limit(20)]);
        }

        return view('meeting-pack.management.show', [
            'plan' => $plan,
        ]);
    }

    public function edit(MeetingPack $plan): View
    {
        $this->authorize('update', $plan);

        return view('meeting-pack.management.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(MeetingPack $plan, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($plan, $request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを更新しました。');
    }

    public function destroy(MeetingPack $plan, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $action($plan);

        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    public function publish(MeetingPack $plan, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(MeetingPack $plan, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(MeetingPack $plan, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書きへ戻しました。');
    }
}
