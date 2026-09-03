<?php

declare(strict_types=1);

namespace Tests\Feature\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回答の編集・削除の検証。
 *
 * - 投稿者本人は回答を編集 / 削除できる
 * - 投稿者以外は編集 / 削除できない(403)
 */
class ManageReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();
        $reply = QaReply::factory()->for($thread)->for($student)->create();

        $response = $this->actingAs($student)->patch(
            route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]),
            ['body' => '更新後の回答'],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答',
        ]);
    }

    public function test_non_author_cannot_update_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();
        $reply = QaReply::factory()->for($thread)->for($author)->create();

        $response = $this->actingAs($other)->patch(
            route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]),
            ['body' => '乗っ取り'],
        );

        $response->assertForbidden();
    }

    public function test_author_can_delete_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($student)->create();
        $reply = QaReply::factory()->for($thread)->for($student)->create();

        $response = $this->actingAs($student)->delete(
            route('qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]),
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_non_author_cannot_delete_reply(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($author)->create();
        $reply = QaReply::factory()->for($thread)->for($author)->create();

        $response = $this->actingAs($other)->delete(
            route('qa-board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]),
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }
}
