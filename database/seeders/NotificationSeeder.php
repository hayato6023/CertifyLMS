<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * アプリ内通知の初期データ。
 *
 * 受講生・コーチに既読 / 未読を混在させた通知を投入し、一覧・未読タブ・
 * ページネーション・行クリック遷移の動作を確認できる状態にする。
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $targets = User::query()
            ->whereIn('role', [UserRole::Student->value, UserRole::Coach->value])
            ->limit(5)
            ->get();

        $samples = [
            [
                'notification_type' => 'qa_reply_received',
                'title' => '質問に回答が届きました',
                'message' => '投稿された質問に新しい回答が付きました。',
            ],
            [
                'notification_type' => 'chat_message_received',
                'title' => '新しいメッセージがあります',
                'message' => 'コーチからチャットメッセージが届きました。',
            ],
            [
                'notification_type' => 'meeting_reserved',
                'title' => '面談が予約されました',
                'message' => '面談の予約が確定しました。日時をご確認ください。',
            ],
            [
                'notification_type' => 'admin_announcement',
                'title' => '運営からのお知らせ',
                'message' => 'メンテナンスのお知らせです。詳細をご確認ください。',
            ],
        ];

        foreach ($targets as $user) {
            foreach ($samples as $i => $sample) {
                $user->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\SeededNotification',
                    'data' => $sample,
                    // 偶数番目は既読、奇数番目は未読にして混在させる
                    'read_at' => $i % 2 === 0 ? now()->subDays($i + 1) : null,
                    'created_at' => now()->subDays($i),
                    'updated_at' => now()->subDays($i),
                ]);
            }
        }
    }
}
