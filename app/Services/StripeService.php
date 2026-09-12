<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe 連携サービス。Checkout セッション作成と Webhook 署名検証を担う。
 *
 * テストでは本サービスをモックに差し替えて外部通信・署名検証を遮断する
 * (createCheckoutSession をスタブ、constructWebhookEvent を検証済みイベント配列で代替)。
 */
class StripeService
{
    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    /**
     * 追加面談購入の Checkout セッションを作成し、session_id と決済 URL を返す。
     *
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Payment $payment, MeetingPack $pack, string $successUrl, string $cancelUrl): array
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $payment->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $pack->price,
                    'product_data' => ['name' => $pack->name],
                ],
            ]],
            'metadata' => ['payment_id' => $payment->id],
        ]);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /**
     * Webhook ペイロードの署名を検証し、イベントを配列で返す。
     *
     * @return array<string, mixed>
     *
     * @throws \Throwable 署名不正 / パース失敗
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): array
    {
        $event = Webhook::constructEvent(
            $payload,
            $signatureHeader,
            (string) config('services.stripe.webhook_secret'),
        );

        return $event->toArray();
    }
}
