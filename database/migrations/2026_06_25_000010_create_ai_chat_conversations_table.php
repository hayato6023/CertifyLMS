<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談の会話。受講生本人がオーナー。
 *
 * enrollment_id / section_id は相談を開始した文脈(資格 / 教材)。どちらも任意。
 * auto_title=true の間は AI が内容に応じてタイトルを自動更新する(受講生が手動編集すると false)。
 * last_message_at は履歴の並び順に使う。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->string('title')->default('新しい相談');
            $table->boolean('auto_title')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_conversations');
    }
};
