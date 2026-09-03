<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 質問掲示板の回答(QaReply)の Controller。受講生 / コーチが投稿・編集・削除する。
 *
 * 回答投稿・編集の認可は FormRequest 側で Policy に委譲する。
 *
 * @see QaReplyPolicy
 */
class QaReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        /** @var User $author */
        $author = $request->user();
        $reply = $action($thread, $author, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $reply);
        $action($reply);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
