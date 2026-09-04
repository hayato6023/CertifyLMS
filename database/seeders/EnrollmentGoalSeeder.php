<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 開発用 個人学習目標シーダー。
 *
 * **設計思想(状態網羅 + 固定アカウント)**:
 *
 * 1. **受講中(learning)の Enrollment に目標を散布**: 達成済・未達成を混在させ、
 *    期日あり/なしをばらつかせる(視覚区別・並び順・達成マーク/解除・編集/削除の確認)。
 *
 * 2. **固定 student の受講登録には確実に複数目標を用意**: 「自分の目標」動線を確認する。
 *
 * 依存順序: `UserSeeder` → `EnrollmentSeeder` → 本 Seeder。
 */
final class EnrollmentGoalSeeder extends Seeder
{
    public function run(): void
    {
        $enrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::Learning->value)
            ->get();

        if ($enrollments->isEmpty()) {
            $this->command?->warn('EnrollmentGoalSeeder: 受講中の Enrollment が存在しません。先に EnrollmentSeeder を実行してください。');

            return;
        }

        $titles = [
            '過去問 5 年分を解き終える',
            '苦手分野を week 単位で潰す',
            '模試で 80% を安定して取る',
            '毎日 1 時間の学習を継続する',
            'テキストを一周する',
        ];

        foreach ($enrollments as $index => $enrollment) {
            // 各受講登録に 2〜3 件の目標(達成済 1 + 未達成 1〜2)
            $this->makeGoal($enrollment, $titles[$index % count($titles)], achieved: true, daysToDeadline: -7);
            $this->makeGoal($enrollment, $titles[($index + 1) % count($titles)], achieved: false, daysToDeadline: 14);

            if ($index % 2 === 0) {
                $this->makeGoal($enrollment, $titles[($index + 2) % count($titles)], achieved: false, daysToDeadline: null);
            }
        }
    }

    private function makeGoal(Enrollment $enrollment, string $title, bool $achieved, ?int $daysToDeadline): void
    {
        EnrollmentGoal::create([
            'enrollment_id' => $enrollment->id,
            'title' => $title,
            'description' => null,
            'target_date' => $daysToDeadline !== null ? Carbon::now()->addDays($daysToDeadline)->format('Y-m-d') : null,
            'achieved_at' => $achieved ? Carbon::now()->subDays(random_int(1, 10)) : null,
        ]);
    }
}
