<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\ListStudentMeetingsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListStudentMeetingsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_own_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $result = app(ListStudentMeetingsAction::class)($student, 'upcoming');

        $this->assertTrue($result->contains('id', $own->id));
        $this->assertFalse($result->contains('id', $other->id));
    }

    public function test_past_filter_excludes_future_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->create();
        $future = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $result = app(ListStudentMeetingsAction::class)($student, 'past');

        $this->assertTrue($result->contains('id', $past->id));
        $this->assertFalse($result->contains('id', $future->id));
    }
}
