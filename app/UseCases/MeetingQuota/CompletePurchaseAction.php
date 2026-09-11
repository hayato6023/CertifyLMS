<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Stripe 決済完了を受けて、購入分の面談回数を加算するユースケース。
 *
 * 冪等性: 既に completed の Payment は再処理しない(Webhook 重複でも二重加算しない)。
 * 加算は MeetingQuotaTransaction(Purchased) として起票し、残数集計に反映させる。
 */
final class CompletePurchaseAction
{
    public function __invoke(Payment $payment): Payment
    {
        // 既に完了済みなら何もしない(Webhook 再送の冪等性)
        if ($payment->status === PaymentStatus::Completed) {
            return $payment;
        }

        return DB::transaction(function () use ($payment) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === PaymentStatus::Completed) {
                return $locked;
            }

            $transaction = MeetingQuotaTransaction::create([
                'user_id' => $locked->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $locked->meeting_count,
                'note' => '追加面談パック購入',
                'occurred_at' => now(),
            ]);

            $locked->forceFill([
                'status' => PaymentStatus::Completed->value,
                'meeting_quota_transaction_id' => $transaction->id,
                'paid_at' => now(),
            ])->save();

            return $locked;
        });
    }
}
