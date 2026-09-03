<?php

declare(strict_types=1);

namespace Tests\Feature\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者モデレーション(admin/qa-board)の検証。
 *
 * - 管理者は公開停止中の資格を含む全資格のスレッドを横断閲覧できる
 * - 管理者は任意のスレッド・回答を削除できる
 * - 受講生 / コーチは管理者モデレーション画面にアクセスできない(403)
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_moderation_index(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();
        $author = User::factory()->student()->inProgress()->create();
        QaThread::factory()->for($draft)->for($author)->create(['title' => 'DRAFT_THREAD']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();
        // 管理者は公開停止中の資格のスレッドも閲覧できる
        $response->assertSee('DRAFT_THREAD');
    }

    public function test_admin_can_delete_thread(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_delete_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();
        $reply = QaReply::factory()->for($thread)->for($author)->create();

        $response = $this->actingAs($admin)->delete(
            route('admin.qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]),
        );

        $response->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_student_cannot_access_moderation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('admin.qa-board.index'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_access_moderation(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($coach)->get(route('admin.qa-board.index'));

        $response->assertForbidden();
    }
}
