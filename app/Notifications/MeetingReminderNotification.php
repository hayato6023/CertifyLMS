<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 予約済み面談のリマインダー通知(前日 / 開始 1 時間前)。アプリ内(database)＋メール(mail)。
 *
 * window は 'eve'(前日)または 'one_hour_before'(1 時間前)。文言をウィンドウで出し分ける。
 */
final class MeetingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting,
        private readonly string $window,
    ) {}

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
        $when = $this->meeting->scheduled_at?->format('Y/m/d H:i');

        return [
            'notification_type' => 'meeting_reminder',
            'title' => $this->title(),
            'message' => "面談予定: {$when}",
            'meeting_id' => $this->meeting->id,
            'window' => $this->window,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->meeting->scheduled_at?->format('Y/m/d H:i');

        return (new MailMessage)
            ->subject('【CertifyLMS】'.$this->title())
            ->line('予約済みの面談が近づいています。')
            ->line("面談予定: {$when}");
    }

    private function title(): string
    {
        return $this->window === 'eve'
            ? '【前日リマインダー】明日、面談の予定があります'
            : '【まもなく】1 時間後に面談の予定があります';
    }
}
