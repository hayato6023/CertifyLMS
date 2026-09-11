<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student()->inProgress(),
            'meeting_pack_id' => MeetingPack::factory(),
            'stripe_session_id' => 'cs_test_'.Str::random(24),
            'amount' => 3000,
            'meeting_count' => 3,
            'status' => PaymentStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Completed, 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed]);
    }
}
