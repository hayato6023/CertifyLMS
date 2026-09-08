<?php

declare(strict_types=1);

namespace Tests\Feature\GoogleCalendar;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google カレンダー連携(S-A-01)の検証。API はモック(Http::fake)。
 *
 * - コーチのみ連携フローに入れる(受講生は 403)
 * - OAuth callback: state 照合 → トークン交換 → 連携保存
 * - state 不一致(なりすまし)は連携しない
 * - 連携解除
 * - busy 取得 / イベント作成は失敗してもフォールバック(例外を投げない)
 */
class ConnectGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_start_oauth(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('settings.google-calendar.redirect'))
            ->assertRedirect();

        $this->assertNotNull(session('google_oauth_state'));
    }

    public function test_student_cannot_access_oauth(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }

    public function test_callback_stores_credential(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'access-xyz',
                'refresh_token' => 'refresh-xyz',
                'expires_in' => 3600,
            ], 200),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)
            ->withSession(['google_oauth_state' => 'state-123'])
            ->get(route('settings.google-calendar.callback', ['state' => 'state-123', 'code' => 'auth-code']))
            ->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'access_token' => 'access-xyz',
            'calendar_id' => 'primary',
        ]);
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        Http::fake();
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)
            ->withSession(['google_oauth_state' => 'expected-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'forged-state', 'code' => 'auth-code']))
            ->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseMissing('google_credentials', ['user_id' => $coach->id]);
        Http::assertNothingSent();
    }

    public function test_coach_can_disconnect(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'a',
            'refresh_token' => 'r',
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseMissing('google_credentials', ['user_id' => $coach->id]);
    }

    public function test_busy_times_falls_back_on_api_error(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(null, 500),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $credential = GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'a',
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);

        $service = app(GoogleCalendarService::class);
        $busy = $service->busyTimes($credential, now(), now()->addDay());

        // API 失敗時は空配列(= 従来の空き判定にフォールバック)
        $this->assertSame([], $busy);
    }
}
