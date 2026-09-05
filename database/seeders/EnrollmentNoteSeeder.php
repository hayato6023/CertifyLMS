<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * コーチメモの初期データ。
 *
 * 複数の受講登録に、コーチ / 管理者が作成したメモを混在させて投入する
 * (編集 / 削除の出し分け・管理者の越境操作・受講生への非表示の確認用)。
 */
class EnrollmentNoteSeeder extends Seeder
{
    public function run(): void
    {
        $coaches = User::query()->where('role', UserRole::Coach->value)->limit(3)->get();
        $admin = User::query()->where('role', UserRole::Admin->value)->first();

        if ($coaches->isEmpty()) {
            return;
        }

        $enrollments = Enrollment::query()->limit(6)->get();

        foreach ($enrollments as $index => $enrollment) {
            $author = $coaches[$index % $coaches->count()];

            EnrollmentNote::factory()->count(2)->create([
                'enrollment_id' => $enrollment->id,
                'user_id' => $author->id,
            ]);

            // 一部の受講登録には管理者メモも混在させる
            if ($admin !== null && $index % 3 === 0) {
                EnrollmentNote::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'user_id' => $admin->id,
                ]);
            }
        }
    }
}
