<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google カレンダー連携サービス。
 *
 * OAuth 認可 URL 生成 / 認可コードのトークン交換 / カレンダーイベントの作成・削除 /
 * 指定期間の busy(予定あり)時刻取得を担う。外部通信はすべて Laravel HTTP クライアント経由で、
 * テストでは Http::fake() でモックする。イベント作成 / 削除 / busy 取得は失敗しても例外を投げず
 * フォールバック(面談機能の根幹を止めない)。
 */
final class GoogleCalendarService
{
    private const OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_BASE = 'https://www.googleapis.com/calendar/v3';

    /**
     * OAuth 認可 URL を生成する。state は CSRF / なりすまし防止用。
     */
    public function authorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::OAUTH_AUTH_URL.'?'.$params;
    }

    /**
     * 認可コードをアクセストークン等に交換する。
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function exchangeCode(string $code): array
    {
        $res = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'code' => $code,
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'redirect_uri' => (string) config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        return [
            'access_token' => (string) ($res['access_token'] ?? ''),
            'refresh_token' => $res['refresh_token'] ?? null,
            'expires_in' => isset($res['expires_in']) ? (int) $res['expires_in'] : null,
        ];
    }

    /**
     * コーチの Google 連携を保存する(既存があれば上書き)。
     */
    public function storeCredential(User $coach, array $token): GoogleCredential
    {
        return GoogleCredential::updateOrCreate(
            ['user_id' => $coach->id],
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'calendar_id' => 'primary',
                'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds($token['expires_in']) : null,
                'connected_at' => now(),
            ],
        );
    }

    /**
     * 面談予定を Google カレンダーへ登録し、作成されたイベント ID を返す(失敗時は null)。
     */
    public function createEvent(GoogleCredential $credential, Meeting $meeting): ?string
    {
        try {
            $res = Http::withToken($credential->access_token)
                ->post(self::CALENDAR_BASE."/calendars/{$credential->calendar_id}/events", [
                    'summary' => '面談',
                    'start' => ['dateTime' => $meeting->scheduled_at?->toIso8601String()],
                    'end' => ['dateTime' => $meeting->scheduled_at?->copy()->addMinutes(30)->toIso8601String()],
                ])->throw()->json();

            return isset($res['id']) ? (string) $res['id'] : null;
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event creation failed', ['meeting_id' => $meeting->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * 面談予定を Google カレンダーから削除する(失敗しても無視)。
     */
    public function deleteEvent(GoogleCredential $credential, string $eventId): void
    {
        try {
            Http::withToken($credential->access_token)
                ->delete(self::CALENDAR_BASE."/calendars/{$credential->calendar_id}/events/{$eventId}")
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Google Calendar event deletion failed', ['event_id' => $eventId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 指定期間で予定が入っている時間帯(busy)を返す。失敗時は空配列(= 従来の空き判定にフォールバック)。
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function busyTimes(GoogleCredential $credential, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        try {
            $res = Http::withToken($credential->access_token)
                ->post(self::CALENDAR_BASE.'/freeBusy', [
                    'timeMin' => $from->format(\DateTimeInterface::RFC3339),
                    'timeMax' => $to->format(\DateTimeInterface::RFC3339),
                    'items' => [['id' => $credential->calendar_id]],
                ])->throw()->json();

            $busy = $res['calendars'][$credential->calendar_id]['busy'] ?? [];

            return array_map(fn ($b) => ['start' => (string) $b['start'], 'end' => (string) $b['end']], $busy);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar freeBusy failed', ['user_id' => $credential->user_id, 'error' => $e->getMessage()]);

            return [];
        }
    }
}
