<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 相談のメッセージ。role=user(受講生) / assistant(AI)。
 *
 * status=completed / error。AI 応答に失敗しても user メッセージは残す(status=error の assistant を添える)。
 * meta には運用観測用メタデータ(モデル名 / トークン数 / 応答時間)を格納する(受講生には表示しない)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_chat_conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->string('status')->default('completed');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ai_chat_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
