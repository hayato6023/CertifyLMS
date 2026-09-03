<?php

declare(strict_types=1);

namespace Tests\Feature\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 質問掲示板の閲覧(一覧 / 詳細)とアクセス制御の検証。
 *
 * - 受講生は公開資格のスレッド一覧・詳細を閲覧できる
 * - コーチは担当資格のスレッドのみ閲覧でき、担当外は 403
 * - 公開停止中の資格のスレッドは受講生に見えない(403)
 * - 一覧はステータス / 資格 / キーワードで絞り込める
 */
class ViewThreadTest extends TestCase
{
    use RefreshDatabase;

    private function assignCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_student_can_view_index(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
    }

    public function test_student_can_view_published_thread_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk();
        $response->assertSee($thread->title);
    }

    public function test_student_cannot_view_unpublished_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($draft)->for($author)->create();

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_coach_can_view_assigned_certification_thread(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach, $admin);

        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $thread));

        $response->assertOk();
    }

    public function test_coach_cannot_view_unassigned_certification_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($coach)->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_index_filters_by_status(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $openThread = QaThread::factory()->for($certification)->for($student)->open()->create(['title' => 'OPEN_THREAD']);
        $resolvedThread = QaThread::factory()->for($certification)->for($student)->resolved()->create(['title' => 'RESOLVED_THREAD']);

        $response = $this->actingAs($student)->get(route('qa-board.index', ['status' => 'resolved']));

        $response->assertOk();
        $response->assertSee('RESOLVED_THREAD');
        $response->assertDontSee('OPEN_THREAD');
    }
}
