<?php

declare(strict_types=1);

namespace Tests\Feature\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `POST /qa-board/{thread}/replies` (回答投稿) の検証。
 *
 * - 受講生は公開資格のスレッドに回答できる
 * - コーチは担当資格のスレッドに回答できる / 担当外は 403
 * - 管理者は公開ボードに入れない(403)
 * - body 必須・文字数上限
 */
class StoreReplyTest extends TestCase
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

    public function test_student_can_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答本文です。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $replier->id,
            'body' => '回答本文です。',
        ]);
    }

    public function test_assigned_coach_can_reply(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->assignCoach($certification, $coach, $admin);

        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'コーチからの回答です。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当外コーチの回答',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_body_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors('body');
    }
}
