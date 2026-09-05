<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\DispatchAnnouncementAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者お知らせ Controller(管理者のみ、ルートの role:admin で担保)。
 *
 * 配信履歴の一覧 / 詳細と、新規配信フォーム / 配信実行を提供する。
 * 配信は不可逆のため、編集 / 削除 / 再配信のアクションは持たない。
 */
final class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcement.management.index', [
            'announcements' => Announcement::query()
                ->with(['targetCertification', 'targetUser', 'createdBy'])
                ->latest('dispatched_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('announcement.management.create', [
            'certifications' => Certification::query()->orderBy('name')->get(),
            'students' => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::InProgress->value)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, DispatchAnnouncementAction $action): RedirectResponse
    {
        $announcement = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', "お知らせを配信しました（{$announcement->dispatched_count} 件）。");
    }

    public function show(Announcement $announcement): View
    {
        return view('announcement.management.show', [
            'announcement' => $announcement->load(['targetCertification', 'targetUser', 'createdBy']),
        ]);
    }
}
