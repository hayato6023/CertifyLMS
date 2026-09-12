<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * 追加面談購入(Stripe Checkout)Controller。学習中受講生のみ(ルートで担保)。
 *
 * select: 公開中の面談パック一覧 / create: Payment(pending)作成 + Checkout へ送出 / success: 完了画面。
 * 非公開パックは URL 直指定でも購入不可。
 */
final class MeetingQuotaCheckoutController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function select(): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => MeetingPack::query()
                ->where('status', MeetingPackStatus::Published->value)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meeting_pack_id' => ['required', 'string', 'exists:meeting_packs,id'],
        ]);

        $pack = MeetingPack::query()->findOrFail($validated['meeting_pack_id']);

        // 公開中でない面談パックは購入不可(URL 直指定を拒否)
        abort_unless($pack->status === MeetingPackStatus::Published, Response::HTTP_FORBIDDEN);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'meeting_count' => $pack->meeting_count,
            'status' => PaymentStatus::Pending,
        ]);

        $session = $this->stripe->createCheckoutSession(
            $payment,
            $pack,
            route('meeting-quota.checkout.success'),
            route('meeting-quota.checkout.select'),
        );

        $payment->update(['stripe_session_id' => $session['id']]);

        return redirect()->away($session['url']);
    }

    public function success(): View
    {
        return view('meeting-quota.success');
    }
}
