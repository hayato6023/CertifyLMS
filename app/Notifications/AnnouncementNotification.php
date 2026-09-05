<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 管理者お知らせを受講生へ配信する通知。アプリ内(database)＋メール(mail)。
 *
 * 通知詳細ページ(notifications.show)で本文全文を読めるよう、data に body を保持する。
 * notification_type は 'admin_announcement'(Blade がアイコン / ラベルを出し分け)。
 */
final class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Announcement $announcement) {}

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
