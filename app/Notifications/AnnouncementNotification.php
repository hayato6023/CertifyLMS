<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 管理者お知らせを受講生へ配信する通知。アプリ内(database)＋メール(mail)。
 *
 * 通知詳細ページ(notifications.show)で本文全文を読めるよう、data に body を保持する。
 * notification_type は 'admin_announcement'(Blade がアイコン / ラベルを出し分け)。
 *
 * ShouldQueue: 一斉配信は対象受講生数だけ送信をループするため、発火元リクエストを
 * ブロックしないようキューへ逃がす。$afterCommit=true で、お知らせレコードの commit 後にのみ
 * 投入する(ロールバック時に送信が漏れない)。一時失敗は backoff で段階的にリトライする。
 */
final class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** リトライ上限(超過分は failed_jobs へ記録される)。 */
    public int $tries = 3;

    public function __construct(private readonly Announcement $announcement)
    {
        // トランザクション commit 後にのみキュー投入する(Queueable::$afterCommit を設定)。
        $this->afterCommit = true;
    }

    /**
     * 一時的な送信失敗時の段階的な待機(秒)。10s → 30s → 60s。
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => mb_substr($this->announcement->body, 0, 120),
            'body' => $this->announcement->body,
            'announcement_id' => $this->announcement->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【CertifyLMS】'.$this->announcement->title)
            ->line($this->announcement->body);
    }
}
