<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者お知らせ(運営から受講生への一斉配信)の配信履歴。
 *
 * target_type(全受講生 / 資格指定 / ユーザー指定)に応じて対象を解決し、既存の通知基盤で配信する。
 * 配信は不可逆(再配信 / 編集 / 取消なし)のため、dispatched_count / dispatched_at で実績を記録する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->string('target_type');
            $table->foreignUlid('target_certification_id')
                ->nullable()
                ->constrained('certifications')
                ->nullOnDelete();
            $table->foreignUlid('target_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
