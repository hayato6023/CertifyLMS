<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Enums\AnnouncementTargetType;
use App\Mail\InvitationMail;
use App\Models\Announcement;
use App\Models\Invitation;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 通知・メール配信の非同期化(T-A-05)を検証する。
 *
 * - 各 Notification / Mailable が ShouldQueue を実装し、リトライ(tries/backoff)を持つこと
 * - commit 後投入が必要な通知は $afterCommit=true であること
 * - お知らせ一斉配信が発火元リクエスト内で同期送信されず、キュー(SendQueuedNotifications)へ積まれること
 */
class NotificationQueueingTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_and_mail_implement_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AnnouncementNotification(
            Announcement::factory()->make(),
        ));
        $this->assertInstanceOf(ShouldQueue::class, new QaReplyReceivedNotification(
            QaReply::factory()->make(),
        ));
        $this->assertInstanceOf(ShouldQueue::class, new ResetPasswordNotification('token'));
        $this->assertInstanceOf(ShouldQueue::class, new InvitationMail(
            Invitation::factory()->make(),
        ));
    }

    public function test_queueable_notifications_declare_retry_and_backoff(): void
    {
        $announcement = new AnnouncementNotification(Announcement::factory()->make());

        $this->assertSame(3, $announcement->tries);
        $this->assertSame([10, 30, 60], $announcement->backoff());
    }

    public function test_commit_sensitive_notifications_are_after_commit(): void
    {
        // お知らせ・Q&A 返信・招待メールはトランザクション内で発火するため commit 後投入が必須
        $this->assertTrue((new AnnouncementNotification(Announcement::factory()->make()))->afterCommit);
        $this->assertTrue((new QaReplyReceivedNotification(QaReply::factory()->make()))->afterCommit);
        $this->assertTrue((new InvitationMail(Invitation::factory()->make()))->afterCommit);
    }

    public function test_meeting_reminder_declares_retry(): void
    {
        $reminder = new MeetingReminderNotification(
            Meeting::factory()->make(),
            'eve',
        );

        $this->assertInstanceOf(ShouldQueue::class, $reminder);
        $this->assertSame(3, $reminder->tries);
        $this->assertSame([10, 30, 60], $reminder->backoff());
    }

    public function test_announcement_dispatch_pushes_to_queue_instead_of_sending_synchronously(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ])->assertRedirect();

        // お知らせレコードは同期で確定するが、通知送信はキューへ積まれる(リクエストをブロックしない)
        $this->assertDatabaseHas('announcements', [
            'title' => 'メンテナンスのお知らせ',
            'dispatched_count' => 3,
        ]);
        Queue::assertPushed(SendQueuedNotifications::class);
    }
}
