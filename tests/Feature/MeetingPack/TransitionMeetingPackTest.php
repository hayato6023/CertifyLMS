<?php

declare(strict_types=1);

namespace Tests\Feature\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 面談パックの状態遷移と削除ガードの検証。
 *
 * - draft → published(publish) / published → archived(archive) / archived → draft(unarchive)
 * - 不正な順序での状態遷移は 409(ConflictHttpException)
 * - 公開中の面談パックは削除できない / 下書き・アーカイブは削除できる
 */
class TransitionMeetingPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_draft_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.publish', $pack));

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertSame(MeetingPackStatus::Published, $pack->fresh()->status);
    }

    public function test_cannot_publish_already_published_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        // 不正遷移は ConflictHttpException(409)。JSON リクエストでは 409 がそのまま返る
        // (Web リクエストでは Handler が 302 リダイレクト + フラッシュに変換する流儀)。
        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.publish', $pack));

        $response->assertStatus(409);
        $this->assertSame(MeetingPackStatus::Published, $pack->fresh()->status);
    }

    public function test_archive_published_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.archive', $pack));

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertSame(MeetingPackStatus::Archived, $pack->fresh()->status);
    }

    public function test_cannot_archive_draft_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.archive', $pack));

        $response->assertStatus(409);
        $this->assertSame(MeetingPackStatus::Draft, $pack->fresh()->status);
    }

    public function test_unarchive_archived_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.unarchive', $pack));

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertSame(MeetingPackStatus::Draft, $pack->fresh()->status);
    }

    public function test_cannot_unarchive_published_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.unarchive', $pack));

        $response->assertStatus(409);
    }

    public function test_can_delete_draft_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $pack));

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $pack->id]);
    }

    public function test_cannot_delete_published_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $pack));

        $response->assertForbidden();
        $this->assertDatabaseHas('meeting_packs', ['id' => $pack->id]);
    }
}
