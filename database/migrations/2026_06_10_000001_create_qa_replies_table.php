<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板スレッドに対する回答。受講生・コーチが投稿する(管理者は回答不可)。
 *
 * 親スレッド削除時は cascade で物理削除する(削除履歴は保持しない)。
 * 投稿者は物理参照(restrictOnDelete)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qa_thread_id')
                ->constrained('qa_threads')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['qa_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_replies');
    }
};
