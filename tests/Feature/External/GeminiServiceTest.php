<?php

declare(strict_types=1);

namespace Tests\Feature\External;

use App\Exceptions\AiChat\GeminiException;
use App\Services\GeminiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Gemini AI チャット連携(S-A-02)の外部 API モックテスト。
 *
 * Gemini は HTTP クライアント直叩きのため Http::fake でスタブする。正常系に加え、通信エラー・
 * 空応答・応答構造の欠落・API キー未設定・送信プロンプト構造を網羅する。
 * external グループとして分離実行できるようにし、未モックの実通信は preventStrayRequests で失敗させる。
 */
#[Group('external')]
class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // モックし忘れた外部通信はテストを失敗させる(実 API を叩かない担保)
        Http::preventStrayRequests();

        Config::set('ai-chat.gemini.api_key', 'test-key');
        Config::set('ai-chat.gemini.model', 'gemini-2.5-flash');
        Config::set('ai-chat.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta');
    }

    public function test_generate_reply_returns_content_and_meta_on_success(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'AI の回答です。']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 34],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generateReply('あなたは学習コーチです。', [
            ['role' => 'user', 'text' => '二分探索の計算量は?'],
        ]);

        $this->assertSame('AI の回答です。', $result['content']);
        $this->assertSame('gemini-2.5-flash', $result['meta']['model']);
        $this->assertSame(12, $result['meta']['prompt_tokens']);
        $this->assertSame(34, $result['meta']['completion_tokens']);
        $this->assertIsInt($result['meta']['latency_ms']);
    }

    public function test_sends_expected_prompt_structure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        app(GeminiService::class)->generateReply('システム文脈', [
            ['role' => 'user', 'text' => '質問1'],
            ['role' => 'model', 'text' => '回答1'],
        ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            // systemInstruction にシステム文脈、contents に history が role/parts 構造で載る
            return str_contains($request->url(), ':generateContent')
                && str_contains($request->url(), 'key=test-key')
                && $body['systemInstruction']['parts'][0]['text'] === 'システム文脈'
                && $body['contents'][0]['role'] === 'user'
                && $body['contents'][0]['parts'][0]['text'] === '質問1'
                && $body['contents'][1]['role'] === 'model'
                && $body['contents'][1]['parts'][0]['text'] === '回答1';
        });
    }

    public function test_throws_when_api_key_missing(): void
    {
        Config::set('ai-chat.gemini.api_key', '');

        $this->expectException(GeminiException::class);

        try {
            app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);
        } catch (GeminiException $e) {
            // API キー未設定は upstreamStatus を持たない
            $this->assertNull($e->upstreamStatus);
            throw $e;
        }
    }

    public function test_throws_on_upstream_error_status(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(null, 503),
        ]);

        try {
            app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);
            $this->fail('GeminiException が投げられるはず');
        } catch (GeminiException $e) {
            $this->assertSame(503, $e->upstreamStatus);
        }
    }

    public function test_throws_on_connection_error(): void
    {
        // 接続例外(名前解決失敗など)を再現
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host');
        });

        try {
            app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);
            $this->fail('GeminiException が投げられるはず');
        } catch (GeminiException $e) {
            // 接続失敗は upstreamStatus null
            $this->assertNull($e->upstreamStatus);
        }
    }

    public function test_throws_on_empty_candidates(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200),
        ]);

        $this->expectException(GeminiException::class);

        app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);
    }

    public function test_throws_on_blank_text_in_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '']]]]],
            ], 200),
        ]);

        $this->expectException(GeminiException::class);

        app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);
    }

    public function test_success_with_missing_usage_metadata_yields_null_tokens(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '回答']]]]],
                // usageMetadata なし
            ], 200),
        ]);

        $result = app(GeminiService::class)->generateReply('ctx', [['role' => 'user', 'text' => 'x']]);

        $this->assertSame('回答', $result['content']);
        $this->assertNull($result['meta']['prompt_tokens']);
        $this->assertNull($result['meta']['completion_tokens']);
    }
}
