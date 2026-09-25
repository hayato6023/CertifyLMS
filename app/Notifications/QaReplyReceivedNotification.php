<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 質問掲示板のスレッドに回答が投稿されたことを、スレッド投稿者へ知らせる通知(S-B-01 依存)。
 *
 * アプリ内(database)＋メール(mail)で配信する。回答者本人には送らない
 * (発火側で投稿者 != 回答者を担保する)。
 *
 * ShouldQueue: 回答投稿リクエストからメール送信を切り離す。$afterCommit=true で、回答レコードの
 * commit 後にのみ投入する(ロールバック時に通知が漏れない)。一時失敗は backoff でリトライする。
 */
final class QaReplyReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** リトライ上限(超過分は failed_jobs へ記録される)。 */
    public int $tries = 3;

    public function __construct(private readonly QaReply $reply)
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
