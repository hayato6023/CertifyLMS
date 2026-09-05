<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 予約済み面談のリマインダー通知を配信する定期実行コマンド。
 *
 * --window=eve             … 前日(翌日開催分)にリマインダー
 * --window=one_hour_before … 開始 1 時間前(直近 1 時間以内に開始する分)にリマインダー
 *
 * 重複配信防止のため、送信済みのウィンドウ列(reminder_eve_sent_at / reminder_one_hour_sent_at)が
 * NULL のものだけを対象にし、配信後に時刻を記録する。二重起動・再実行しても再送しない。
 */
final class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window=eve : eve または one_hour_before}';

    protected $description = '予約済み面談の前日 / 1時間前リマインダーを配信する';

    public function handle(): int
    {
        $window = (string) $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error("不正な --window です: {$window}（eve | one_hour_before）");

            return self::FAILURE;
        }

        [$from, $to, $sentColumn] = $this->resolveWindow($window);

        $count = 0;

        Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereNull($sentColumn)
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['student', 'coach'])
            ->chunkById(100, function ($meetings) use ($window, $sentColumn, &$count): void {
                foreach ($meetings as $meeting) {
                    $this->notifyParticipants($meeting, $window);
                    $meeting->forceFill([$sentColumn => now()])->save();
                    $count++;
                }
            });

        $this->info("面談リマインダー({$window})を {$count} 件配信しました。");

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveWindow(string $window): array
    {
        if ($window === 'eve') {
            return [
                now()->addDay()->startOfDay(),
                now()->addDay()->endOfDay(),
                'reminder_eve_sent_at',
            ];
        }

        return [
            now(),
            now()->addMinutes(60),
            'reminder_one_hour_sent_at',
        ];
    }

    private function notifyParticipants(Meeting $meeting, string $window): void
    {
        foreach ([$meeting->student, $meeting->coach] as $participant) {
            if ($participant === null) {
                continue;
            }

            // 退会済みユーザーには配信しない(利用状態による配信対象の制御)
            if ($participant->status === UserStatus::Withdrawn) {
                continue;
            }

            $participant->notify(new MeetingReminderNotification($meeting, $window));
        }
    }
}
