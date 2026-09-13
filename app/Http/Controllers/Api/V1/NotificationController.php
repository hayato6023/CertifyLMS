<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpFoundation\Response;

/**
 * 通知 JSON API (v1)。Sanctum Cookie 認証で保護し、認証ユーザー本人の通知のみ返す。
 *
 * TopBar のベルクリックで開く通知ポップオーバー(JS フロント)から fetch される。
 *
 * NOTE(S-A-05 着手中): API 側(一覧 / 既読化 / 全件既読)を先行実装。
 *   JS ポップオーバー・バッジ動的増減とフロントの結合テストは未実装。
 */
final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(20)->get();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn (DatabaseNotification $n) => $this->present($n))->all(),
        ]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorizeOwnership($request, $notification);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : [];

        return [
            'id' => $n->id,
            'type' => $data['notification_type'] ?? null,
            'title' => $data['title'] ?? '通知',
            'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
            'action_url' => $data['action_url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }

    private function authorizeOwnership(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        $isOwner = $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();

        abort_unless($isOwner, Response::HTTP_NOT_FOUND);
    }
}
