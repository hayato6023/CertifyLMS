<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板(Q&A)のスレッド。受講生が資格を選んで投稿する公開質問。
 *
 * status は open / resolved の 2 値(既定 open)。resolved_at は解決済にした時刻。
 * 資格・投稿者は物理参照(restrictOnDelete)。スレッド削除時は配下の回答も物理削除する(qa_replies 側で cascade)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};
