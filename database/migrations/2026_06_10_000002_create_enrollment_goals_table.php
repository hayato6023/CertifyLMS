<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録(Enrollment)配下の個人学習目標。受講生本人が自由入力で立てる。
 *
 * target_date は目標期日(任意)、achieved_at は達成マーク時刻(未達成は null)。
 * 親 Enrollment 削除時は cascade で物理削除する(履歴は残さない)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'achieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_goals');
    }
};
