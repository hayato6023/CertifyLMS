<?php

declare(strict_types=1);

namespace Tests\Feature\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * コーチメモ(S-B-07)の CRUD・認可の検証。
 *
 * - 担当コーチ / 管理者は担当受講登録にメモを追加できる
 * - コーチは自分のメモのみ編集 / 削除でき、他コーチのメモは操作不可
 * - 管理者は任意のメモを編集 / 削除できる(越境)
 * - 担当外コーチ・受講生は操作できない
 */
class ManageNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function assignCoach(User $coach, Certification $certification): void
    {
        $coach->assignedCertifications()->attach($certification, [
            'assigned_by_user_id' => $this->admin->id,
            'assigned_at' => now(),
        ]);
    }

    private function enrollment(): Enrollment
    {
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();

        return Enrollment::factory()->for($student)->for($certification)->create();
    }

    public function test_assigned_coach_can_add_note(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($coach, $enrollment->certification);

        $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '最近チャットの応答が遅れている。次回面談で確認する。',
        ])->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_admin_can_add_note(): void
    {
        $enrollment = $this->enrollment();

        $this->actingAs($this->admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '運営観察メモ。',
        ])->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_unassigned_coach_cannot_add_note(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '担当外のメモ',
        ])->assertForbidden();
    }

    public function test_student_cannot_add_note(): void
    {
        $enrollment = $this->enrollment();
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '受講生のメモ',
        ])->assertForbidden();
    }

    public function test_body_is_required(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($coach, $enrollment->certification);

        $this->actingAs($coach)
            ->from(route('enrollments.show', $enrollment))
            ->post(route('enrollments.notes.store', $enrollment), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_author_can_update_own_note(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($coach, $enrollment->certification);
        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);

        $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), [
            'body' => '更新後のメモ',
        ])->assertRedirect(route('enrollments.show', $enrollment->id));

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id, 'body' => '更新後のメモ']);
    }

    public function test_coach_cannot_update_others_note(): void
    {
        $enrollment = $this->enrollment();
        $author = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($author, $enrollment->certification);
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($otherCoach, $enrollment->certification);
        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($otherCoach)->patch(route('enrollment-notes.update', $note), [
            'body' => '他人のメモを書き換え',
        ])->assertForbidden();
    }

    public function test_admin_can_update_any_note(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($coach, $enrollment->certification);
        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);

        $this->actingAs($this->admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者が是正',
        ])->assertRedirect(route('enrollments.show', $enrollment->id));

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id, 'body' => '管理者が是正']);
    }

    public function test_author_can_delete_own_note(): void
    {
        $enrollment = $this->enrollment();
        $coach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($coach, $enrollment->certification);
        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
        ]);

        $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('enrollments.show', $enrollment->id));

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_coach_cannot_delete_others_note(): void
    {
        $enrollment = $this->enrollment();
        $author = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($author, $enrollment->certification);
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $this->assignCoach($otherCoach, $enrollment->certification);
        $note = EnrollmentNote::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($otherCoach)->delete(route('enrollment-notes.destroy', $note))
            ->assertForbidden();
    }
}
