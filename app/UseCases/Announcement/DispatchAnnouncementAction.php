<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * お知らせを作成し、対象受講生へ配信(アプリ内＋メール)するユースケース。
 *
 * 配信対象は「全受講生 / 資格指定 / ユーザー指定」の 3 種類で、いずれも
 * 受講中(InProgress)の受講生に限定する。配信は不可逆で、実績(件数 / 時刻)を記録する。
 *
 * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
 */
final class DispatchAnnouncementAction
{
    public function __invoke(User $admin, array $validated): Announcement
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);
        $recipients = $this->resolveRecipients($targetType, $validated);

        return DB::transaction(function () use ($admin, $validated, $targetType, $recipients) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? ($validated['target_certification_id'] ?? null)
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? ($validated['target_user_id'] ?? null)
                    : null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
            ]);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new AnnouncementNotification($announcement));
            }

            return $announcement;
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(AnnouncementTargetType $type, array $validated): Collection
    {
        $base = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        return match ($type) {
            AnnouncementTargetType::AllStudents => $base->get(),
            AnnouncementTargetType::Certification => $base
                ->whereIn('id', Enrollment::query()
                    ->where('certification_id', $validated['target_certification_id'] ?? null)
                    ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
                    ->pluck('user_id'))
                ->get(),
            AnnouncementTargetType::User => $base
                ->where('id', $validated['target_user_id'] ?? null)
                ->get(),
        };
    }
}
