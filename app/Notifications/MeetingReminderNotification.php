<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 予約済み面談のリマインダー通知(前日 / 開始 1 時間前)。アプリ内(database)＋メール(mail)。
 *
 * window は 'eve'(前日)または 'one_hour_before'(1 時間前)。文言をウィンドウで出し分ける。
 *
 * ShouldQueue: 定期実行コマンドが対象面談を chunk でループ送信するため、各送信をキューへ逃がして
 * コマンドの実行時間と外部依存(メール送信)を発火元から切り離す。一時失敗は backoff でリトライする。
 */
final class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** リトライ上限(超過分は failed_jobs へ記録される)。 */
    public int $tries = 3;

    public function __construct(
        private readonly Meeting $meeting,
        private readonly string $window,
    ) {}

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
