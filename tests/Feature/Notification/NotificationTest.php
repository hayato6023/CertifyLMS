<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 通知基盤(S-B-04)の検証。
 *
 * - 一覧(全件 / 未読タブ)表示
 * - 通知の既読化(単体 / 全件)
 * - 他人宛の通知は閲覧・既読化できない(404)
 * - Q&A スレッドへの回答でスレッド投稿者に通知が発火する(回答者本人には発火しない)
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user, ?string $readAt = null): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\SeededNotification',
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => 'テスト通知',
                'message' => '本文',
            ],
            'read_at' => $readAt,
        ]);

        return $id;
    }

    public function test_user_can_view_notification_index(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->seedNotification($user);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }

    public function test_unread_tab_shows_only_unread(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->seedNotification($user, readAt: now()->toDateTimeString());
        $this->seedNotification($user, readAt: null);

        $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']))
            ->assertOk()
            ->assertViewHas('unreadCount', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $id = $this->seedNotification($user);

        $this->actingAs($user)->post(route('notifications.markAsRead', $id))->assertRedirect();

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->seedNotification($user);
        $this->seedNotification($user);

        $this->actingAs($user)->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_cannot_read_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $id = $this->seedNotification($owner);

        $this->actingAs($other)->post(route('notifications.markAsRead', $id))->assertNotFound();
    }

    public function test_guest_cannot_view_notifications(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_qa_reply_notifies_thread_owner(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $owner = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->coach()->inProgress()->create();
        $replier->assignedCertifications()->attach($certification, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $thread = QaThread::factory()->for($owner)->for($certification)->create();

        $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), [
            'body' => 'こちらが回答です。',
        ]);

        Notification::assertSentTo($owner, QaReplyReceivedNotification::class);
    }

    public function test_qa_reply_does_not_notify_self(): void
    {
        Notification::fake();

        $certification = Certification::factory()->published()->create();
        $owner = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->for($certification)->create();

        $thread = QaThread::factory()->for($owner)->for($certification)->create();

        $this->actingAs($owner)->post(route('qa-board.replies.store', $thread), [
            'body' => '自己回答です。',
        ]);

        Notification::assertNotSentTo($owner, QaReplyReceivedNotification::class);
    }
}
