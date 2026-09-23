<?php

declare(strict_types=1);

namespace Tests\Feature\External;

use App\Services\StripeService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Stripe Webhook 署名検証(S-A-03)の外部連携テスト。
 *
 * Stripe は公式 SDK を使うため、他テストのように Service を Mockery で丸ごと差し替えるのではなく、
 * ここでは「正規署名を自前で生成するヘルパー」を用意して実 StripeService::constructWebhookEvent を通し、
 * 署名検証そのものの振る舞い(正規署名は通る / 不正署名・署名欠落・期限切れは弾く)を検証する。
 * external グループとして分離実行できるようにする。
 */
#[Group('external')]
class StripeWebhookSignatureTest extends TestCase
{
    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.stripe.webhook_secret', $this->secret);
    }

    /**
     * Stripe 形式の署名ヘッダ `t=<ts>,v1=<HMAC-SHA256(ts.payload)>` を生成する。
     */
    private function signature(string $payload, ?int $timestamp = null, ?string $secret = null): string
    {
        $timestamp ??= time();
        $secret ??= $this->secret;
        $signedPayload = "{$timestamp}.{$payload}";
        $v1 = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$v1}";
    }

    private function payload(): string
    {
        return json_encode([
            'id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => ['payment_id' => 'pay_1']]],
        ], JSON_THROW_ON_ERROR);
    }

    public function test_valid_signature_is_accepted(): void
    {
        $payload = $this->payload();
        $event = app(StripeService::class)->constructWebhookEvent($payload, $this->signature($payload));

        $this->assertSame('checkout.session.completed', $event['type']);
        $this->assertSame('pay_1', $event['data']['object']['metadata']['payment_id']);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $payload = $this->payload();
        $signature = $this->signature($payload);

        // 署名生成後に本文を改ざん → 署名不一致で例外
        $tampered = str_replace('pay_1', 'pay_hacked', $payload);

        $this->expectException(\Throwable::class);
        app(StripeService::class)->constructWebhookEvent($tampered, $signature);
    }

    public function test_wrong_secret_signature_is_rejected(): void
    {
        $payload = $this->payload();
        $signature = $this->signature($payload, null, 'whsec_wrong_secret');

        $this->expectException(\Throwable::class);
        app(StripeService::class)->constructWebhookEvent($payload, $signature);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->expectException(\Throwable::class);
        app(StripeService::class)->constructWebhookEvent($this->payload(), '');
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $payload = $this->payload();
        // 既定の許容誤差(300 秒)を大きく超える過去のタイムスタンプ
        $signature = $this->signature($payload, time() - 100000);

        $this->expectException(\Throwable::class);
        app(StripeService::class)->constructWebhookEvent($payload, $signature);
    }
}
