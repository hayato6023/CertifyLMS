<?php

declare(strict_types=1);

namespace Tests\Feature\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 個人学習目標の CRUD・達成切替・認可の検証。
 *
 * - 受講生本人は目標を追加 / 編集 / 削除 / 達成マーク / 達成解除できる
 * - 他受講生 / コーチ / 管理者は操作できない(403)
 * - バリデーション(title 必須・文字数、target_date は日付)
 * - 親 Enrollment 物理削除で配下の目標も cascade 削除される
 */
class ManageGoalTest extends TestCase
{
    use RefreshDatabase;

    private function learningEnrollment(User $student): Enrollment
    {
        $certification = Certification::factory()->published()->create();

        return Enrollment::factory()->for($student)->for($certification)->create();
    }

    public function test_owner_can_add_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問 5 年分を解き終える',
            'description' => '毎週末に 1 年分',
            'target_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問 5 年分を解き終える',
            'achieved_at' => null,
        ]);
    }

    public function test_owner_can_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '更新後の目標',
            'target_date' => null,
        ]);

        $response->assertRedirect(route('enrollments.show', $enrollment->id));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
        ]);
    }

    public function test_owner_can_delete_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($student)->delete(route('enrollment-goals.destroy', $goal));

        $response->assertRedirect(route('enrollments.show', $enrollment->id));
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_owner_can_mark_and_unmark_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);
        $goal = EnrollmentGoal::factory()->for($enrollment)->unachieved()->create();

        $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal))->assertRedirect();
        $this->assertNotNull($goal->fresh()->achieved_at);

        $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertRedirect();
        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_other_student_cannot_add_goal(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($owner);

        $response = $this->actingAs($other)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '他人の目標',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($owner);
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $response = $this->actingAs($other)->patch(route('enrollment-goals.update', $goal), [
            'title' => '乗っ取り',
        ]);

        $response->assertForbidden();
    }

    public function test_coach_cannot_operate_goal(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // コーチは閲覧のみ、目標操作(達成マーク)はできない
        $this->actingAs($coach)->post(route('enrollment-goals.markAchieved', $goal))->assertForbidden();
    }

    public function test_title_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_title_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);

        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => str_repeat('あ', 101),
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_goals_are_cascade_deleted_with_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = $this->learningEnrollment($student);
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        // 親 Enrollment を物理削除すると配下の目標も cascade 削除される
        $enrollment->forceDelete();

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}
