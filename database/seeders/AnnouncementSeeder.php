<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 管理者お知らせの配信履歴 初期データ。
 *
 * 配信対象の 3 種類(全受講生 / 資格指定 / ユーザー指定)それぞれの履歴を投入する
 * (一覧・詳細・対象バッジの表示確認用)。
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin->value)->first();
        if ($admin === null) {
            return;
        }

        $studentCount = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->count();

        // 全受講生向け
        Announcement::factory()->create([
            'title' => '年末年始の運営休止について',
            'target_type' => AnnouncementTargetType::AllStudents,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $studentCount,
            'dispatched_at' => now()->subDays(3),
        ]);

        // 資格指定向け
        $certification = Certification::query()->first();
        if ($certification !== null) {
            Announcement::factory()->create([
                'title' => '【重要】試験範囲の更新のお知らせ',
                'target_type' => AnnouncementTargetType::Certification,
                'target_certification_id' => $certification->id,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => 1,
                'dispatched_at' => now()->subDays(1),
            ]);
        }

        // ユーザー指定向け
        $student = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();
        if ($student !== null) {
            Announcement::factory()->create([
                'title' => '個別フォローのご連絡',
                'target_type' => AnnouncementTargetType::User,
                'target_user_id' => $student->id,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => 1,
                'dispatched_at' => now(),
            ]);
        }
    }
}
