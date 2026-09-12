<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\StripeService;
use App\UseCases\MeetingQuota\CompletePurchaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe Webhook 受信 Controller(認証なし、署名検証のみが正当性の担保)。
 *
 * checkout.session.completed を受けて該当 Payment を完了処理する。署名不正は 400。
 * 想定外のイベントは 200 で無視し、処理を破綻させない。重複配信は CompletePurchaseAction の冪等性で吸収。
 */
final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly CompletePurchaseAction $completePurchase,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (\Throwable) {
            // 署名不正 / 改ざん / パース失敗
            return response()->json(['error' => 'invalid signature'], 400);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event);
        }

        // 想定外イベントも 200 で受理(Stripe の再送を止める)
        return response()->json(['received' => true]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleCheckoutCompleted(array $event): void
    {
        $object = $event['data']['object'] ?? [];
        $paymentId = $object['metadata']['payment_id'] ?? ($object['client_reference_id'] ?? null);

        if ($paymentId === null) {
            return;
        }

        $payment = Payment::query()->find($paymentId);
        if ($payment === null) {
            return;
        }

        if (isset($object['payment_intent'])) {
            $payment->forceFill(['stripe_payment_intent_id' => (string) $object['payment_intent']])->save();
        }

        ($this->completePurchase)($payment);
    }
}
