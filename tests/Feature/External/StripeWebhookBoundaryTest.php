<?php

declare(strict_types=1);

namespace Tests\Feature\External;

use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Stripe Webhook 受信(S-A-03)の境界系テスト。
 *
 * 署名検証は StripeService を Mockery で差し替えてバイパスし、Controller 側のイベントハンドリングの
 * 境界(payment_id 欠落 / 存在しない payment / 未対応イベント種別 / client_reference_id フォールバック)を
 * 検証する。冪等性・正常系・署名不正は PurchaseMeetingQuotaTest でカバー済みのため、ここでは境界のみ。
 */
#[Group('external')]
class StripeWebhookBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeEvent(array $event): void
    {
        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('constructWebhookEvent')->andReturn($event);
        $this->app->instance(StripeService::class, $mock);
    }

    public function test_event_without_payment_id_is_ignored_with_200(): void
    {
        $this->fakeEvent([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => []]],
        ]);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_event_with_unknown_payment_id_is_ignored_with_200(): void
    {
        $this->fakeEvent([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => ['payment_id' => 'pay_missing']]],
        ]);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unhandled_event_type_is_received_with_200(): void
    {
        $this->fakeEvent([
            'type' => 'payment_intent.created',
            'data' => ['object' => []],
        ]);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])
            ->assertOk()
            ->assertJson(['received' => true]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_client_reference_id_is_used_as_fallback_payment_id(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'meeting_count' => 3,
            'status' => PaymentStatus::Pending,
        ]);

        // metadata.payment_id は無く client_reference_id のみ持つイベント
        $this->fakeEvent([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['client_reference_id' => $payment->id]],
        ]);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::Completed->value,
        ]);
        $this->assertSame(1, MeetingQuotaTransaction::query()
            ->where('user_id', $student->id)->where('type', 'purchased')->count());
    }
}
