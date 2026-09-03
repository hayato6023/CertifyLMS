<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板(公開コンテキスト)の Controller。受講生 / コーチ向け。
 *
 * 受講生は公開済資格すべて、コーチは担当資格のみを閲覧・操作できる(閲覧スコープは
 * viewableCertificationIds で算出)。管理者モデレーションは Admin\QaThreadModerationController。
 *
 * @see QaThreadPolicy
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        /** @var User $viewer */
        $viewer = $request->user();

        $certificationIds = $this->viewableCertificationIds($viewer);

        $threads = $action(
            certificationIds: $certificationIds,
            status: $validated['status'] ?? null,
            certificationId: $validated['certification_id'] ?? null,
            keyword: $validated['keyword'] ?? null,
        );

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => [
                'status' => $validated['status'] ?? '',
                'certification_id' => $validated['certification_id'] ?? '',
                'keyword' => $validated['keyword'] ?? '',
            ],
            'certifications' => $this->viewableCertifications($viewer),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(StoreRequest $request): View
    {
        // authorize は StoreRequest::authorize() で実施済み。資格セレクトは公開済のみ。
        return view('qa-thread.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();
        $thread = $action($author, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->loadMissing(['user', 'certification', 'replies.user'])
            ->loadCount('replies');

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);
        $thread->loadMissing('certification');

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);
        $action($thread);

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);
        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済にしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);
        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }

    /**
     * 閲覧者が閲覧可能な資格 ID を返す。受講生は公開済資格すべて、コーチは担当資格のみ。
     *
     * @return array<int, string>
     */
    private function viewableCertificationIds(User $viewer): array
    {
        if ($viewer->role === UserRole::Coach) {
            return $viewer->coachingCertificationIds();
        }

        return Certification::query()->published()->pluck('id')->all();
    }

    /**
     * 絞り込みチップ用の閲覧可能な資格コレクション。
     *
     * @return Collection<int, Certification>
     */
    private function viewableCertifications(User $viewer): Collection
    {
        if ($viewer->role === UserRole::Coach) {
            return $viewer->assignedCertifications()->orderBy('name')->get();
        }

        return Certification::query()->published()->orderBy('name')->get();
    }
}
