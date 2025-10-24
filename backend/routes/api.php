<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// 系统健康检查
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0',
        'message' => '财务管理系统API运行正常'
    ]);
});
// 公开路由
Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    // 认证相关
    Route::post('/auth/refresh', [\App\Http\Controllers\Api\AuthController::class, 'refresh']);
    Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/auth/me', [\App\Http\Controllers\Api\AuthController::class, 'me']);
    
    // 支付渠道
    Route::get('/channels', [\App\Http\Controllers\Api\ChannelController::class, 'index']);
    Route::get('/channels/{channel}', [\App\Http\Controllers\Api\ChannelController::class, 'show']);
    // 地点
    Route::get('/locations', [\App\Http\Controllers\Api\LocationController::class, 'index']);
    
    // 交易记录
    Route::get('/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
    Route::get('/transactions/statistics', [\App\Http\Controllers\Api\TransactionController::class, 'statistics']);
    Route::post('/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'store']);
    Route::get('/transactions/{transaction}', [\App\Http\Controllers\Api\TransactionController::class, 'show']);
    Route::post('/transactions/batch', [\App\Http\Controllers\Api\TransactionController::class, 'batchStore']);
    // 首页本金
    Route::get('/home/principal', [\App\Http\Controllers\Api\HomeController::class, 'principal']);

    // 结余管理
    Route::get('/settlements/check-today', [\App\Http\Controllers\Api\SettlementController::class, 'checkToday']);
    Route::post('/settlements/verify-password', [\App\Http\Controllers\Api\SettlementController::class, 'verifyPassword']);
    Route::get('/settlements/preview', [\App\Http\Controllers\Api\SettlementController::class, 'preview']);
    Route::post('/settlements', [\App\Http\Controllers\Api\SettlementController::class, 'store']);
    Route::get('/settlements', [\App\Http\Controllers\Api\SettlementController::class, 'index']);
    Route::get('/settlements/{id}', [\App\Http\Controllers\Api\SettlementController::class, 'show']);

    
    // 草稿管理
    Route::get('/drafts', [\App\Http\Controllers\Api\DraftController::class, 'index']);
    Route::post('/drafts', [\App\Http\Controllers\Api\DraftController::class, 'store']);
    Route::get('/drafts/{draft}', [\App\Http\Controllers\Api\DraftController::class, 'show']);
    Route::put('/drafts/{draft}', [\App\Http\Controllers\Api\DraftController::class, 'update']);
    Route::delete('/drafts/{draft}', [\App\Http\Controllers\Api\DraftController::class, 'destroy']);
    Route::post('/drafts/{draft}/submit', [\App\Http\Controllers\Api\DraftController::class, 'submit']);
    Route::post('/drafts/batch-sync', [\App\Http\Controllers\Api\DraftController::class, 'batchSync']);
    
    // 图片管理
    Route::post('/images', [\App\Http\Controllers\Api\ImageController::class, 'store']);
    Route::get('/images/{image}', [\App\Http\Controllers\Api\ImageController::class, 'show']);
    Route::delete('/images/{image}', [\App\Http\Controllers\Api\ImageController::class, 'destroy']);
    Route::post('/images/batch', [\App\Http\Controllers\Api\ImageController::class, 'batchUpload']);
    
    // 后台管理路由
    Route::prefix('admin')->group(function () {
        // 余额管理
        Route::get('/balance/overview', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'overview']);
        Route::get('/balance/channel/{channelId}', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'channelDetail']);
        Route::get('/balance/history/{channelId}', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'history']);
        Route::post('/balance/adjust', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'adjust']);
        Route::get('/balance/adjustments', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'adjustments']);
        Route::post('/balance/recalculate', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'recalculate']);
        
        // 数据导出
        Route::post('/export/transactions', [\App\Http\Controllers\Api\Admin\ExportController::class, 'transactions']);
        Route::post('/export/balances', [\App\Http\Controllers\Api\Admin\ExportController::class, 'balances']);
        Route::post('/export/channel-summary', [\App\Http\Controllers\Api\Admin\ExportController::class, 'channelSummary']);

        // 财务报表（日/月/年结）
        Route::post('/reports/daily-settlement', [\App\Http\Controllers\Api\ReportController::class, 'dailySettlement']);
        Route::post('/reports/monthly-settlement', [\App\Http\Controllers\Api\ReportController::class, 'monthlySettlement']);
        Route::post('/reports/yearly-settlement', [\App\Http\Controllers\Api\ReportController::class, 'yearlySettlement']);
        
        // 系统设置
        Route::get('/settings', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'index']);
        Route::put('/settings', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'update']);
        Route::get('/settings/{key}', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'show']);
        Route::post('/settings/reset', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'reset']);
        Route::get('/system/info', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'systemInfo']);
        // 数据清理
        Route::post('/data/cleanup', [\App\Http\Controllers\Api\CleanupController::class, 'cleanup']);
    });
});

// 测试路由 (仅开发环境)
if (app()->environment('local', 'development')) {
    Route::get('/test', function () {
        return response()->json([
            'message' => '测试路由正常',
            'environment' => app()->environment(),
            'controllers' => [
                'AuthController' => class_exists(\App\Http\Controllers\Api\AuthController::class) ? '存在' : '不存在',
                'TransactionController' => class_exists(\App\Http\Controllers\Api\TransactionController::class) ? '存在' : '不存在',
                'DraftController' => class_exists(\App\Http\Controllers\Api\DraftController::class) ? '存在' : '不存在',
                'ChannelController' => class_exists(\App\Http\Controllers\Api\ChannelController::class) ? '存在' : '不存在',
                'ImageController' => class_exists(\App\Http\Controllers\Api\ImageController::class) ? '存在' : '不存在',
            ]
        ]);
    });
}
