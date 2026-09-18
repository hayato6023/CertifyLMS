<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\ShowMeetingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowMeetingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loads_detail_relations(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = app(ShowMeetingAction::class)($meeting);

        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->relationLoaded('meetingMemo'));
        $this->assertSame($coach->id, $result->coach->id);
    }
}
