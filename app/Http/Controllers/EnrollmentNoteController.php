<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * コーチメモ Controller。担当コーチ / 管理者が受講登録配下のメモを管理する。
 *
 * 追加は受講登録配下、編集 / 削除はメモ単位。認可は EnrollmentNotePolicy に委譲する。
 * 受講生は Policy によりすべて拒否される。
 */
final class EnrollmentNoteController extends Controller
{
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $this->authorize('create', [EnrollmentNote::class, $enrollment]);

        $action($enrollment, $request->user(), $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'メモを追加しました。');
    }

    public function edit(EnrollmentNote $enrollmentNote): View
    {
        $this->authorize('update', $enrollmentNote);

        return view('enrollment-note.edit', ['note' => $enrollmentNote]);
    }

    public function update(EnrollmentNote $enrollmentNote, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->authorize('update', $enrollmentNote);

        $action($enrollmentNote, $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollmentNote->enrollment_id)
            ->with('success', 'メモを更新しました。');
    }

    public function destroy(EnrollmentNote $enrollmentNote, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $enrollmentNote);

        $enrollmentId = $enrollmentNote->enrollment_id;
        $action($enrollmentNote);

        return redirect()
            ->route('enrollments.show', $enrollmentId)
            ->with('success', 'メモを削除しました。');
    }
}
