<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\ReserveMeetingAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReserveMeetingActionTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_reserves_meeting_and_consumes_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $meeting = app(ReserveMeetingAction::class)($enrollment, $scheduledAt, '相談したい');

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertSame($student->id, $meeting->student_id);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
        ]);
    }

    public function test_throws_when_quota_insufficient(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 0]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->expectException(InsufficientMeetingQuotaException::class);

        app(ReserveMeetingAction::class)($enrollment, $scheduledAt, null);
    }

    public function test_throws_when_slot_out_of_availability(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        // 有効枠(09:00-18:00)外の 20:00 を狙う
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(20, 0);

        $this->expectException(MeetingOutOfAvailabilityException::class);

        app(ReserveMeetingAction::class)($enrollment, $scheduledAt, null);
    }
}
