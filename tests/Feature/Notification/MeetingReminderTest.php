<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 面談リマインダー(S-B-09)の検証。
 *
 * - 前日 / 1 時間前ウィンドウで予約済み面談の当事者(受講生 + コーチ)に配信
 * - 対象外(当日でない / キャンセル済み)は配信しない
 * - 重複防止: 2 回実行しても 1 回しか送らない
 * - 退会済みユーザーには配信しない
 */
class MeetingReminderTest extends TestCase
{
    use RefreshDatabase;

    private function reservedMeeting(\DateTimeInterface $scheduledAt, ?User $student = null, ?User $coach = null): Meeting
    {
        $student ??= User::factory()->student()->inProgress()->create();
        $coach ??= User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();

        return Meeting::factory()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $student->id,
            'coach_id' => $coach->id,
            'scheduled_at' => $scheduledAt,
            'status' => MeetingStatus::Reserved,
            'reminder_eve_sent_at' => null,
            'reminder_one_hour_sent_at' => null,
        ]);
    }

    public function test_eve_window_notifies_participants(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->reservedMeeting(now()->addDay()->setTime(10, 0), $student, $coach);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertSuccessful();

        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }

    public function test_one_hour_window_notifies_participants(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $this->reservedMeeting(now()->addMinutes(30), $student);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->assertSuccessful();

        Notification::assertSentTo($student, MeetingReminderNotification::class);
    }

    public function test_meeting_outside_window_is_not_notified(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        // 3 日後の面談は前日ウィンドウの対象外
        $this->reservedMeeting(now()->addDays(3)->setTime(10, 0), $student);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertSuccessful();

        Notification::assertNotSentTo($student, MeetingReminderNotification::class);
    }

    public function test_canceled_meeting_is_not_notified(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $meeting = $this->reservedMeeting(now()->addDay()->setTime(10, 0), $student);
        $meeting->update(['status' => MeetingStatus::Canceled]);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertSuccessful();

        Notification::assertNotSentTo($student, MeetingReminderNotification::class);
    }

    public function test_reminder_is_not_sent_twice(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $this->reservedMeeting(now()->addDay()->setTime(10, 0), $student);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);

        Notification::assertSentToTimes($student, MeetingReminderNotification::class, 1);
    }

    public function test_withdrawn_user_is_not_notified(): void
    {
        Notification::fake();
        $student = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->reservedMeeting(now()->addDay()->setTime(10, 0), $student, $coach);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);

        Notification::assertNotSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }
}
