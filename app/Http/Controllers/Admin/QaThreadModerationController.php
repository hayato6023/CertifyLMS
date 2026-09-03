<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\QaThread\AdminIndexRequest;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction as ReplyDestroyAction;
use App\UseCases\QaThread\DestroyAction as ThreadDestroyAction;
use App\UseCases\QaThread\IndexAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板の管理者モデレーション Controller。
 *
 * 管理者は公開停止中の資格を含む全資格のスレッドを横断閲覧でき、任意のスレッド・回答を
 * 削除できる(内容編集 / 解決マーク代行は不可)。公開側と同じ Blade を共用し、
 * Blade 側が `request()->routeIs('admin.*')` で表示を出し分ける。
 *
 * 認可は `role:admin` ミドルウェアで担保する(公開側の QaThreadPolicy は student / coach 専用のため使わない)。
 */
class QaThreadModerationController extends Controller
{
    public function index(AdminIndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        // 管理者は全資格を横断閲覧する
        $certificationIds = Certification::query()->pluck('id')->all();

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
            'certifications' => Certification::query()->orderBy('name')->get(),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread): View
    {
        $thread->loadMissing(['user', 'certification', 'replies.user'])
            ->loadCount('replies');

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function destroy(QaThread $thread, ThreadDestroyAction $action): RedirectResponse
    {
        $action($thread);

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function destroyReply(QaThread $thread, QaReply $reply, ReplyDestroyAction $action): RedirectResponse
    {
        $action($reply);

        return redirect()
            ->route('admin.qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
