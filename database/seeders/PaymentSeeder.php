<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 追加面談購入の決済記録 初期データ。
 *
 * 決済状態の異なる購入記録(完了 / 保留 / 失敗)を投入する
 * (状態ごとの履歴表示・完了分のみ残数反映の確認用)。
 */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        $pack = MeetingPack::query()->where('status', MeetingPackStatus::Published->value)->first()
            ?? MeetingPack::query()->first();

        if ($student === null || $pack === null) {
            return;
        }

        foreach ([PaymentStatus::Completed, PaymentStatus::Pending, PaymentStatus::Failed] as $status) {
            Payment::factory()->state([
                'status' => $status,
                'paid_at' => $status === PaymentStatus::Completed ? now() : null,
            ])->create([
                'user_id' => $student->id,
                'meeting_pack_id' => $pack->id,
                'amount' => $pack->price,
                'meeting_count' => $pack->meeting_count,
            ]);
        }
    }
}
