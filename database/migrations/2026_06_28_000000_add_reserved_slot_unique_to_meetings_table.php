<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 同一コーチ・同一時刻の二重予約(B-A-01)を DB 制約で防ぐ。
 *
 * status='reserved' のときだけ (coach_id, scheduled_at) を表す生成列 reserved_slot_key を持たせ、
 * それ以外(canceled / completed)は NULL にする。NULL は UNIQUE 制約の対象外なので、
 * 「予約中の枠は 1 件のみ」を保証しつつ、キャンセル後の同時刻再予約は許容する。
 * 並行予約は UNIQUE 違反として弾かれ、Controller が 409 に変換する。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE meetings
            ADD COLUMN reserved_slot_key VARCHAR(96)
                GENERATED ALWAYS AS (
                    CASE WHEN status = 'reserved'
                        THEN CONCAT(coach_id, '_', scheduled_at)
                        ELSE NULL
                    END
                ) STORED
        SQL);

        Schema::table('meetings', function ($table) {
            $table->unique('reserved_slot_key', 'meetings_reserved_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function ($table) {
            $table->dropUnique('meetings_reserved_slot_unique');
        });
        DB::statement('ALTER TABLE meetings DROP COLUMN reserved_slot_key');
    }
};
