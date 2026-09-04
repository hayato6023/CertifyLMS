<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * アプリ内通知 Controller(受講生・コーチ・管理者共通、本人の通知のみ)。
 *
 * 一覧(全件 / 未読タブ)・詳細・既読化・全件既読を提供する。
 * 他人宛の通知は閲覧・既読化できない(404 として扱う)。
 */
final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $query = $user->notifications();
        if ($tab === 'unread') {
            $query = $user->unreadNotifications();
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function show(Request $request, DatabaseNotification $notification): View
    {
        $this->authorizeOwnership($request, $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return view('notifications.show', ['notification' => $notification]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $actionUrl = $data['action_url'] ?? null;

        if (is_string($actionUrl) && $actionUrl !== '') {
            return redirect()->to($actionUrl);
        }

        return redirect()->route('notifications.show', $notification);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }

    /**
     * 通知が認証ユーザー本人宛でなければ 404。
     */
    private function authorizeOwnership(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        $isOwner = $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();

        abort_unless($isOwner, Response::HTTP_NOT_FOUND);
    }
}
