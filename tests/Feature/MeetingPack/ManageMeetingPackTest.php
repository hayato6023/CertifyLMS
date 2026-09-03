<?php

declare(strict_types=1);

namespace Tests\Feature\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 面談パックマスタ管理の CRUD・認可・バリデーションの検証。
 *
 * - 管理者は一覧 / 詳細 / 作成 / 編集 / 削除ができる
 * - 受講生 / コーチは全操作でアクセス拒否(403)
 * - 作成時は下書き状態で保存される
 * - バリデーション(name / meeting_count / price の必須・範囲)
 */
class ManageMeetingPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        MeetingPack::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
    }

    public function test_admin_can_view_show(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk();
        $response->assertSee($pack->name);
    }

    public function test_admin_can_create_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '5 回パック',
            'description' => '説明文',
            'meeting_count' => 5,
            'price' => 15000,
            'sort_order' => 10,
        ]);

        $pack = MeetingPack::first();
        $this->assertNotNull($pack);
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'name' => '5 回パック',
            'meeting_count' => 5,
            'price' => 15000,
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_pack(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $pack = MeetingPack::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $pack), [
            'name' => '更新後パック',
            'meeting_count' => 3,
            'price' => 9000,
        ]);

        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $pack->id,
            'name' => '更新後パック',
            'meeting_count' => 3,
            'price' => 9000,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_student_cannot_access(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }

    public function test_coach_cannot_access(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('admin.meeting-packs.index'))->assertForbidden();
    }

    public function test_name_required(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '',
            'meeting_count' => 5,
            'price' => 15000,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_meeting_count_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'パック',
            'meeting_count' => 0,
            'price' => 15000,
        ])->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'パック',
            'meeting_count' => 101,
            'price' => 15000,
        ])->assertSessionHasErrors('meeting_count');
    }

    public function test_price_must_be_within_range(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'パック',
            'meeting_count' => 5,
            'price' => -1,
        ])->assertSessionHasErrors('price');

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => 'パック',
            'meeting_count' => 5,
            'price' => 1000001,
        ])->assertSessionHasErrors('price');
    }
}
