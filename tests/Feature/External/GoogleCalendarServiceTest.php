<?php

declare(strict_types=1);

namespace Tests\Feature\External;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Google カレンダー連携(S-A-01)の外部 API モックテスト。
 *
 * Google はカレンダー操作のまとまった単位(トークン交換 / 予定作成 / 予定削除 / freeBusy)を Http::fake で
 * スタブする。正常系に加え、トークン交換失敗、予定作成/削除/空き取得の API エラー時フォールバック、
 * refresh_token / expires_in 欠落の境界を網羅する。
 * external グループとして分離実行でき、未モックの実通信は preventStrayRequests で失敗させる。
 */
#[Group('external')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        Config::set('services.google.client_id', 'client-id');
        Config::set('services.google.client_secret', 'client-secret');
        Config::set('services.google.redirect', 'https://app.test/callback');
    }

    private function credentialFor(User $coach): GoogleCredential
    {
        return GoogleCredential::create([
            'user_id' => $coach->id,
            'access_token' => 'access-token',
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ]);
    }

    public function test_exchange_code_returns_tokens(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'access-xyz',
                'refresh_token' => 'refresh-xyz',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = app(GoogleCalendarService::class)->exchangeCode('auth-code');

        $this->assertSame('access-xyz', $token['access_token']);
        $this->assertSame('refresh-xyz', $token['refresh_token']);
        $this->assertSame(3600, $token['expires_in']);
    }

    public function test_exchange_code_throws_on_token_endpoint_error(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        // exchangeCode は ->throw() するため、失敗時は例外が伝播する
        $this->expectException(RequestException::class);

        app(GoogleCalendarService::class)->exchangeCode('bad-code');
    }

    public function test_store_credential_sets_expiry_from_expires_in(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $credential = app(GoogleCalendarService::class)->storeCredential($coach, [
            'access_token' => 'a',
            'refresh_token' => 'r',
            'expires_in' => 3600,
        ]);

        $this->assertSame('primary', $credential->calendar_id);
        $this->assertNotNull($credential->token_expires_at);
    }

    public function test_store_credential_without_expires_in_keeps_null_expiry(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        // refresh_token / expires_in 欠落の境界: token_expires_at は null のまま
        $credential = app(GoogleCalendarService::class)->storeCredential($coach, [
            'access_token' => 'a',
        ]);

        $this->assertNull($credential->refresh_token);
        $this->assertNull($credential->token_expires_at);
    }

    public function test_create_event_returns_event_id_on_success(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(['id' => 'evt_123'], 200),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $credential = $this->credentialFor($coach);
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay()->startOfHour(),
        ]);

        $eventId = app(GoogleCalendarService::class)->createEvent($credential, $meeting);

        $this->assertSame('evt_123', $eventId);
    }

    public function test_create_event_returns_null_on_api_error(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(null, 500),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $credential = $this->credentialFor($coach);
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDay()->startOfHour(),
        ]);

        // API 失敗時は例外を投げず null にフォールバック
        $this->assertNull(app(GoogleCalendarService::class)->createEvent($credential, $meeting));
    }

    public function test_delete_event_swallows_api_error(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(null, 404),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $credential = $this->credentialFor($coach);

        // 削除失敗(既に消えている等)でも例外を投げない
        app(GoogleCalendarService::class)->deleteEvent($credential, 'evt_removed');

        $this->expectNotToPerformAssertions();
    }

    public function test_busy_times_parses_busy_ranges(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'calendars' => [
                    'primary' => [
                        'busy' => [
                            ['start' => '2026-09-24T10:00:00Z', 'end' => '2026-09-24T11:00:00Z'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $credential = $this->credentialFor($coach);

        $busy = app(GoogleCalendarService::class)->busyTimes($credential, now(), now()->addDay());

        $this->assertCount(1, $busy);
        $this->assertSame('2026-09-24T10:00:00Z', $busy[0]['start']);
        $this->assertSame('2026-09-24T11:00:00Z', $busy[0]['end']);
    }

    public function test_busy_times_falls_back_to_empty_on_error(): void
    {
        Http::fake([
            'www.googleapis.com/*' => Http::response(null, 500),
        ]);

        $coach = User::factory()->coach()->inProgress()->create();
        $credential = $this->credentialFor($coach);

        $this->assertSame([], app(GoogleCalendarService::class)->busyTimes($credential, now(), now()->addDay()));
    }
}
