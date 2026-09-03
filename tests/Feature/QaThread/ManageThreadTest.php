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
 * 質問スレッドの編集・削除・解決状態切り替えの検証。
 *
 * - 投稿者本人はスレッドを編集 / 削除 / 解決 / 未解決に戻す操作ができる
 * - 投稿者以外(別受講生)は編集 / 削除できない(403)
 * - 解決マークで resolved_at が打刻され、未解決に戻すとクリアされる
 */
class ManageThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後のタイトル',
            'body' => '更新後の本文',
        ]);
    }

    public function test_non_author_cannot_update_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($other)->patch(route('qa-board.update', $thread), [
            'title' => '乗っ取り',
            'body' => '本文',
        ]);

        $response->assertForbidden();
    }

    public function test_author_can_delete_thread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();

        $response = $this->actingAs($student)->delete(route('qa-board.destroy', $thread));

        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_non_author_cannot_delete_thread(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();

        $response = $this->actingAs($other)->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_author_can_resolve_and_unresolve(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->open()->create();

        $this->actingAs($student)->post(route('qa-board.resolve', $thread))->assertRedirect();
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);

        $this->actingAs($student)->post(route('qa-board.unresolve', $thread))->assertRedirect();
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_non_author_cannot_resolve(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->open()->create();

        $response = $this->actingAs($other)->post(route('qa-board.resolve', $thread));

        $response->assertForbidden();
    }
}
