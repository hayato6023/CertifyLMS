<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\ListCoachMeetingsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCoachMeetingsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_own_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = app(ListCoachMeetingsAction::class)($coach, 'upcoming');

        $this->assertTrue($result->contains('id', $own->id));
        $this->assertFalse($result->contains('id', $other->id));
    }

    public function test_filters_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $meetingA = Meeting::factory()->reserved()->forCoach($coach)->forStudent($studentA)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $meetingB = Meeting::factory()->reserved()->forCoach($coach)->forStudent($studentB)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = app(ListCoachMeetingsAction::class)($coach, 'upcoming', $studentA->id);

        $this->assertTrue($result->contains('id', $meetingA->id));
        $this->assertFalse($result->contains('id', $meetingB->id));
    }
}
