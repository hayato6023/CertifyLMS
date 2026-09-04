<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講プランマスタ管理の CRUD・認可・バリデーションの検証。
 *
 * - 管理者は一覧 / 詳細 / 作成 / 編集ができる
 * - 受講生 / コーチは全操作でアクセス拒否(403)
 * - 作成時は下書き状態で保存される
 * - バリデーション(name / duration_days / default_meeting_quota の必須・範囲)
 */
class ManagePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        Plan::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('admin.plans.index'))->assertOk();
    }

    public function test_admin_can_view_show_with_subscribers(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->published()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertSee($plan->name);
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '3 ヶ月プラン',
            'description' => '説明文',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'sort_order' => 10,
        ]);

        $plan = Plan::first();
        $this->assertNotNull($plan);
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'name' => '3 ヶ月プラン',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
            'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
            'name' => '更新後プラン',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
        ]);

        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'duration_days' => 180,
            'default_meeting_quota' => 12,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_student_cannot_access(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_coach_cannot_access(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_name_required(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_duration_days_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'プラン',
            'duration_days' => 0,
            'default_meeting_quota' => 6,
        ])->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'プラン',
            'duration_days' => 3651,
            'default_meeting_quota' => 6,
        ])->assertSessionHasErrors('duration_days');
    }

    public function test_default_meeting_quota_required(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'プラン',
            'duration_days' => 90,
        ]);

        $response->assertSessionHasErrors('default_meeting_quota');
    }
}
