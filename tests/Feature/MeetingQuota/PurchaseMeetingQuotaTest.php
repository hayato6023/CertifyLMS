<?php

declare(strict_types=1);

namespace Tests\Feature\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * 追加面談購入(S-A-03)の検証。Stripe はモック(StripeService を差し替え)。
 *
 * - 学習中受講生のみ購入導線に入れる(コーチは不可)
 * - Checkout 作成で Payment(pending)ができ Stripe へ送出
 * - 非公開パックは購入不可(403)
 * - Webhook(署名検証済み)で Payment が completed になり Purchased 加算
 * - Webhook 重複でも二重加算しない(冪等)
 * - 署名不正は 400
 */
class PurchaseMeetingQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function publishedPack(): MeetingPack
    {
        return MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Published,
            'meeting_count' => 3,
            'price' => 3000,
        ]);
    }

    public function test_student_can_view_checkout(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->publishedPack();

        $this->actingAs($student)->get(route('meeting-quota.checkout.select'))->assertOk();
    }

    public function test_coach_cannot_access_checkout(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($coach)->get(route('meeting-quota.checkout.select'))->assertForbidden();
    }

    public function test_create_checkout_makes_pending_payment_and_redirects(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = $this->publishedPack();

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('createCheckoutSession')->once()
            ->andReturn(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/pay/cs_test_123']);
        $this->app->instance(StripeService::class, $mock);

        $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ])->assertRedirect('https://checkout.stripe.com/pay/cs_test_123');

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'status' => PaymentStatus::Pending->value,
            'stripe_session_id' => 'cs_test_123',
            'amount' => 3000,
            'meeting_count' => 3,
        ]);
    }

    public function test_unpublished_pack_cannot_be_purchased(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft]);

        $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ])->assertForbidden();
    }

    public function test_webhook_completes_payment_and_grants_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = $this->publishedPack();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => 3000,
            'meeting_count' => 3,
            'status' => PaymentStatus::Pending,
        ]);

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('constructWebhookEvent')->andReturn([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'metadata' => ['payment_id' => $payment->id],
                'payment_intent' => 'pi_test_1',
            ]],
        ]);
        $this->app->instance(StripeService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => PaymentStatus::Completed->value]);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => 'purchased',
            'amount' => 3,
        ]);
    }

    public function test_webhook_is_idempotent(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'meeting_count' => 3,
            'status' => PaymentStatus::Pending,
        ]);

        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('constructWebhookEvent')->andReturn([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['metadata' => ['payment_id' => $payment->id]]],
        ]);
        $this->app->instance(StripeService::class, $mock);

        // 同じ Webhook を 2 回配信
        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();
        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'sig'])->assertOk();

        // Purchased 加算は 1 回だけ
        $this->assertSame(1, MeetingQuotaTransaction::query()
            ->where('user_id', $student->id)->where('type', 'purchased')->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $mock = Mockery::mock(StripeService::class);
        $mock->shouldReceive('constructWebhookEvent')->andThrow(new \RuntimeException('invalid signature'));
        $this->app->instance(StripeService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [], ['Stripe-Signature' => 'bad'])
            ->assertStatus(400);
    }
}
