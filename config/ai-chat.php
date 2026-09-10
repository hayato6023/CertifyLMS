<?php

declare(strict_types=1);

return [
    /*
    | 機能全体の ON/OFF スイッチ。OFF にすると AI 相談の画面・ルートが利用不可になる。
    */
    'enabled' => (bool) env('AI_CHAT_ENABLED', true),

    /*
    | 受講生 1 人あたりの 1 日の送信回数上限。
    */
    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 30),

    /*
    | AI へ引き継ぐ直近メッセージ件数(会話履歴の窓)。
    */
    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 10),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
];
