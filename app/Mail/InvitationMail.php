<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use App\Services\InvitationTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 招待メール。ShouldQueue で送信を非同期化する。$afterCommit=true で、招待レコードの commit 後にのみ
 * 投入する(ロールバック時にメールが漏れない)。一時失敗は backoff でリトライする。
 */
class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** リトライ上限(超過分は failed_jobs へ記録される)。 */
    public int $tries = 3;

    public function __construct(public Invitation $invitation)
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

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: 'Certify LMS への招待',
        );
    }

    public function content(): Content
    {
        $url = app(InvitationTokenService::class)->generateUrl($this->invitation);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'invitedBy' => $this->invitation->invitedBy,
                'roleLabel' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at,
                'url' => $url,
            ],
        );
    }
}
