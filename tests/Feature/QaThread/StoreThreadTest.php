<?php

declare(strict_types=1);

namespace Tests\Feature\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /qa-board` (質問スレッド投稿) の検証。
 *
 * - 受講生は公開資格に質問を投稿できる(status=open で保存)
 * - コーチ / 管理者は投稿できない(403)
 * - 公開停止中の資格には投稿できない(422)
 * - certification_id / title / body 必須・文字数上限
 */
class StoreThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_post_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'テスト質問タイトル',
            'body' => 'これはテスト用の質問本文です。',
        ]);

        $thread = QaThread::first();
        $this->assertNotNull($thread);
        $response->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => 'テスト質問タイトル',
            'status' => QaThreadStatus::Open->value,
        ]);
        $this->assertNull($thread->resolved_at);
    }

    public function test_coach_cannot_post_thread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'コーチの質問',
            'body' => '本文',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_admin_cannot_access_public_board(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // 管理者は公開ボードのルートグループ(role:student,coach)に入れない
        $response = $this->actingAs($admin)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '管理者の質問',
            'body' => '本文',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_cannot_post_to_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draft->id,
            'title' => '下書き資格への質問',
            'body' => '本文',
        ]);

        $response->assertSessionHasErrors('certification_id');
        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_certification_id_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'title' => 'タイトル',
            'body' => '本文',
        ]);

        $response->assertSessionHasErrors('certification_id');
    }

    public function test_title_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '',
            'body' => '本文',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_title_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => str_repeat('あ', 201),
            'body' => '本文',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_body_required(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'タイトル',
            'body' => '',
        ]);

        $response->assertSessionHasErrors('body');
    }

    public function test_body_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'タイトル',
            'body' => str_repeat('a', 5001),
        ]);

        $response->assertSessionHasErrors('body');
    }
}
