<?php

declare(strict_types=1);

namespace Tests\Feature\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * お知らせ配信(S-B-08)の検証。
 *
 * - 管理者は全受講生 / 資格指定 / ユーザー指定で配信でき、対象受講生に通知が飛ぶ
 * - 受講生 / コーチは管理画面にアクセスできない(403)
 * - 配信履歴(dispatched_count / dispatched_at)が記録される
 * - バリデーション(タイトル / 本文必須、対象 ID の条件付き必須)
 */
class DeliverAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.announcements.index'))->assertOk();
    }

    public function test_student_cannot_access_management(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->actingAs($student)->get(route('admin.announcements.index'))->assertForbidden();
    }

    public function test_coach_cannot_access_management(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($coach)->get(route('admin.announcements.create'))->assertForbidden();
    }

    public function test_admin_can_dispatch_to_all_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $students = User::factory()->count(3)->student()->inProgress()->create();
        User::factory()->student()->graduated()->create(); // 対象外

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'title' => 'メンテナンスのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 3,
        ]);
        Notification::assertSentTo($students[0], AnnouncementNotification::class);
    }

    public function test_dispatch_to_certification_targets_enrolled_only(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $enrolledStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($enrolledStudent)->for($certification)->create();

        $otherStudent = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '試験範囲の更新',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ])->assertRedirect();

        Notification::assertSentTo($enrolledStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($otherStudent, AnnouncementNotification::class);
    }

    public function test_dispatch_to_single_user(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別連絡',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $target->id,
        ])->assertRedirect();

        Notification::assertSentTo($target, AnnouncementNotification::class);
        Notification::assertNotSentTo($other, AnnouncementNotification::class);
    }

    public function test_title_and_body_are_required(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '',
                'body' => '',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_certification_target_requires_certification_id(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => 'タイトル',
                'body' => '本文',
                'target_type' => AnnouncementTargetType::Certification->value,
            ])
            ->assertSessionHasErrors('target_certification_id');
    }
}
