<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 質問掲示板のスレッドに回答が投稿されたことを、スレッド投稿者へ知らせる通知(S-B-01 依存)。
 *
 * アプリ内(database)＋メール(mail)で配信する。回答者本人には送らない
 * (発火側で投稿者 != 回答者を担保する)。
 */
final class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly QaReply $reply) {}

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
        $thread = $this->reply->qaThread;

        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が届きました',
            'message' => mb_substr((string) $this->reply->body, 0, 120),
            'action_url' => route('qa-board.show', $thread),
            'qa_thread_id' => $thread?->id,
            'qa_reply_id' => $this->reply->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->reply->qaThread;

        return (new MailMessage)
            ->subject('【CertifyLMS】質問に回答が届きました')
            ->line('あなたの質問に回答が投稿されました。')
            ->line('質問: '.($thread?->title ?? ''))
            ->action('回答を確認する', route('qa-board.show', $thread));
    }
}
