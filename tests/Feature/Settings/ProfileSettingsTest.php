<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 設定・プロフィール(S-B-06)の検証。
 *
 * - プロフィール表示 / 氏名・自己紹介の更新(全ロール本人)
 * - コーチのみ meeting_url を更新可、受講生は無視される
 * - パスワード変更(現在パスワード確認 + confirmed)
 * - アバターのアップロード / 削除
 * - 修了済受講生も利用できる
 * - 未認証は弾かれる
 */
class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $this->actingAs($user)->get(route('settings.profile'))->assertOk();
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->get(route('settings.profile'))->assertRedirect(route('login'));
    }

    public function test_user_can_update_name_and_bio(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '新しい氏名',
            'bio' => '自己紹介テキスト',
        ]);

        $response->assertRedirect(route('settings.profile'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '新しい氏名',
            'bio' => '自己紹介テキスト',
        ]);
    }

    public function test_email_is_not_changed_via_profile_update(): void
    {
        $user = User::factory()->student()->inProgress()->create(['email' => 'keep@example.com']);

        $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '氏名',
            'email' => 'hacked@example.com',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'keep@example.com']);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $this->actingAs($user)
            ->from(route('settings.profile'))
            ->patch(route('settings.profile.update'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => 'コーチ',
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);
    }

    public function test_student_meeting_url_is_ignored(): void
    {
        $student = User::factory()->student()->inProgress()->create(['meeting_url' => null]);

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '受講生',
            'meeting_url' => 'https://evil.example.com',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', ['id' => $student->id, 'meeting_url' => null]);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        $user = User::factory()->student()->graduated()->create();

        $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '修了後の氏名',
        ])->assertRedirect(route('settings.profile'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '修了後の氏名']);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('current-pass'),
        ]);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'current-pass',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('settings.profile', ['tab' => 'password']));

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('current-pass'),
        ]);

        $this->actingAs($user)
            ->from(route('settings.profile', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-pass',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasErrors('current_password', errorBag: 'updatePassword');

        $this->assertTrue(Hash::check('current-pass', $user->refresh()->password));
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        $user = User::factory()->student()->inProgress()->create([
            'password' => Hash::make('current-pass'),
        ]);

        $this->actingAs($user)
            ->from(route('settings.profile', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'current-pass',
                'password' => 'new-password-123',
                'password_confirmation' => 'mismatch-456',
            ])
            ->assertSessionHasErrors('password', errorBag: 'updatePassword');
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->inProgress()->create(['avatar_url' => null]);

        $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png', 200, 200),
        ])->assertRedirect(route('settings.profile'));

        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertCount(1, Storage::disk('public')->files('avatars'));
    }

    public function test_avatar_upload_rejects_non_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->inProgress()->create();

        $this->actingAs($user)
            ->from(route('settings.profile'))
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->student()->inProgress()->create();

        // まずアップロードしてから削除する
        $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);
        $this->assertNotNull($user->refresh()->avatar_url);

        $this->actingAs($user)->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.profile'));

        $this->assertNull($user->refresh()->avatar_url);
        $this->assertCount(0, Storage::disk('public')->files('avatars'));
    }
}
