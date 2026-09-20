<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | 管理者ダッシュボード集計のキャッシュ
    |--------------------------------------------------------------------------
    |
    | 管理者ダッシュボードの全体 KPI と資格別修了率は全 enrollment を走査する重い集計。
    | 短時間の連続表示で同じ集計を再実行しないよう、既定のキャッシュストアに一定時間キャッシュする。
    | 受講状態の遷移(合格 / 不合格 / 退会 / 新規登録の初回記録)が起きたときは
    | EnrollmentStatusChangeService からキャッシュを無効化し、古い集計値が残らないようにする。
    |
    | TTL は秒。環境変数 DASHBOARD_ADMIN_STATS_CACHE_TTL で調整可能。
    */

    'admin_kpi_cache_key' => 'dashboard:admin:kpi',

    'admin_completion_rate_cache_key' => 'dashboard:admin:completion_rate',

    'admin_stats_cache_ttl' => (int) env('DASHBOARD_ADMIN_STATS_CACHE_TTL', 300),
];
