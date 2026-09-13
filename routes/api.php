<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| JSON API のルート定義。
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================================
// 通知 JSON API (v1) — Sanctum Cookie 認証(S-A-05 着手中)
// ============================================================
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
});
