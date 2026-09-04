<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * プランの状態遷移と削除ガードの検証。
 *
 * - draft → published(publish) / published → archived(archive) / archived → draft(unarchive)
 * - 不正な順序での状態遷移は 409
 * - 削除は「下書き かつ 受講者未紐づき」のみ可。公開中・受講者ありは削除不可
 */
class TransitionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_draft_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Published, $plan->fresh()->status);
    }

    public function test_cannot_publish_published_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();

        $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan))->assertStatus(409);
    }

    public function test_archive_published_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.archive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Archived, $plan->fresh()->status);
    }

    public function test_cannot_archive_draft_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan))->assertStatus(409);
    }

    public function test_unarchive_archived_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(PlanStatus::Draft, $plan->fresh()->status);
    }

    public function test_can_delete_draft_plan_without_subscribers(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_published_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        $response->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_draft_plan_with_subscribers(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        // Policy::delete が false → 403
        $response->assertForbidden();
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }
}
