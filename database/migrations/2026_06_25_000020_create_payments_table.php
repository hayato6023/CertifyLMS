<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 追加面談購入(Stripe)の決済記録。
 *
 * amount / meeting_count は購入時点の面談パックの控え(後からマスタが変わっても監査可能)。
 * stripe_session_id はユニークにし、Webhook 重複でも二重計上しない冪等キーとして使う。
 * 決済完了時に発行した MeetingQuotaTransaction を meeting_quota_transaction_id で紐づける。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('meeting_pack_id')->nullable()->constrained('meeting_packs')->nullOnDelete();
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('meeting_count');
            $table->string('status')->default('pending');
            $table->foreignUlid('meeting_quota_transaction_id')->nullable()->constrained('meeting_quota_transactions')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
