<?php

declare(strict_types=1);

namespace Tests\Feature\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 同一コーチ・同一時刻の二重予約防止(B-A-01)の検証。
 *
 * reserved の (coach_id, scheduled_at) は一意(生成列 + UNIQUE)。
 * - 予約中の枠に同一コーチ・同一時刻でもう 1 件 reserved を作ると UNIQUE 違反
 * - キャンセル済みが同時刻にあっても、同じ枠を新規予約できる(NULL は対象外)
 * - 別コーチ / 別時刻は当然両立する
 */
class DoubleBookingTest extends TestCase
{
    use RefreshDatabase;

    private function makeReserved(User $coach, User $student, \DateTimeInterface $at, MeetingStatus $status = MeetingStatus::Reserved): Meeting
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();

        return Meeting::factory()->create([
            'enrollment_id' => $enrollment->id,
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'scheduled_at' => $at,
            'status' => $status,
        ]);
    }

    public function test_same_coach_same_time_reserved_is_rejected(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $s1 = User::factory()->student()->inProgress()->create();
        $s2 = User::factory()->student()->inProgress()->create();
        $at = now()->addDay()->setTime(10, 0);

        $this->makeReserved($coach, $s1, $at);

        $this->expectException(QueryException::class);
        $this->makeReserved($coach, $s2, $at);
    }

    public function test_canceled_slot_can_be_rebooked(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $s1 = User::factory()->student()->inProgress()->create();
        $s2 = User::factory()->student()->inProgress()->create();
        $at = now()->addDay()->setTime(10, 0);

        // 同時刻の canceled は生成列が NULL のため UNIQUE 対象外
        $this->makeReserved($coach, $s1, $at, MeetingStatus::Canceled);

        // 同じコーチ・同じ時刻を新規に reserved で予約できる
        $meeting = $this->makeReserved($coach, $s2, $at);
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'status' => MeetingStatus::Reserved->value]);
    }

    public function test_different_coach_same_time_is_allowed(): void
    {
        $coach1 = User::factory()->coach()->inProgress()->create();
        $coach2 = User::factory()->coach()->inProgress()->create();
        $s1 = User::factory()->student()->inProgress()->create();
        $s2 = User::factory()->student()->inProgress()->create();
        $at = now()->addDay()->setTime(10, 0);

        $this->makeReserved($coach1, $s1, $at);
        $meeting = $this->makeReserved($coach2, $s2, $at);

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id]);
    }
}
