<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 通知 JSON API v1(S-A-05)の検証。Sanctum 認証で保護され、本人の通知のみ返す。
 *
 * - 未認証は 401
 * - index は本人の通知のみ + 未読件数を返す
 * - 本人通知は既読化でき、他者の通知 ID は 404
 * - 全件既読で未読が 0 になる
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user, ?string $readAt = null): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\SeededNotification',
            'data' => ['notification_type' => 'admin_announcement', 'title' => 'テスト', 'message' => '本文'],
            'read_at' => $readAt,
        ]);

        return $id;
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_index_returns_own_notifications_with_unread_count(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->seedNotification($user);
        $this->seedNotification($user, readAt: now()->toDateTimeString());

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(2, 'notifications');
    }

    public function test_index_excludes_other_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $this->seedNotification($other);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'notifications');
    }

    public function test_mark_as_read_own_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $id = $this->seedNotification($user);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_cannot_mark_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $id = $this->seedNotification($owner);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
    }

    public function test_mark_all_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->seedNotification($user);
        $this->seedNotification($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
