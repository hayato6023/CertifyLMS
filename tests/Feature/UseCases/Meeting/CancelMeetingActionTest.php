<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\CancelMeetingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelMeetingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_reserved_meeting_and_refunds_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        app(CancelMeetingAction::class)($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertSame($student->id, $meeting->fresh()->canceled_by_user_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_throws_when_meeting_not_reserved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $this->expectException(MeetingStatusTransitionException::class);

        app(CancelMeetingAction::class)($meeting, $student);
    }

    public function test_throws_when_meeting_already_started(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinutes(10),
        ]);

        $this->expectException(MeetingAlreadyStartedException::class);

        app(CancelMeetingAction::class)($meeting, $student);
    }
}
