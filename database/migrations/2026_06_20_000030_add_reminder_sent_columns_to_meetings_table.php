<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談リマインダーの重複配信防止用カラム。
 *
 * 前日 / 1 時間前 のリマインダーをそれぞれ送信済みにした時刻を記録し、
 * 定期実行が二重起動・再実行されても同じウィンドウでは再送しないようにする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('reminder_eve_sent_at')->nullable()->after('scheduled_at');
            $table->timestamp('reminder_one_hour_sent_at')->nullable()->after('reminder_eve_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['reminder_eve_sent_at', 'reminder_one_hour_sent_at']);
        });
    }
};
