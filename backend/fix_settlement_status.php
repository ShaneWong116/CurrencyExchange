<?php

/**
 * 修复交易记录的 settlement_status 字段
 * 确保所有未结算的记录都有正确的 settlement_status 值
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "开始修复交易记录的 settlement_status 字段...\n\n";

try {
    // 查询所有 settlement_status 为 NULL 的记录
    $nullCount = DB::table('transactions')
        ->whereNull('settlement_status')
        ->count();
    
    echo "发现 {$nullCount} 条 settlement_status 为 NULL 的记录\n";
    
    if ($nullCount > 0) {
        // 更新为 'unsettled'
        $updated = DB::table('transactions')
            ->whereNull('settlement_status')
            ->update(['settlement_status' => 'unsettled']);
        
        echo "已更新 {$updated} 条记录的 settlement_status 为 'unsettled'\n";
    }
    
    // 查询所有 settlement_id 为 NULL 且 settlement_status 不是 'unsettled' 的记录
    $inconsistentCount = DB::table('transactions')
        ->whereNull('settlement_id')
        ->where('settlement_status', '!=', 'unsettled')
        ->count();
    
    echo "\n发现 {$inconsistentCount} 条 settlement_id 为 NULL 但 settlement_status 不是 'unsettled' 的记录\n";
    
    if ($inconsistentCount > 0) {
        // 修复不一致的记录
        $fixed = DB::table('transactions')
            ->whereNull('settlement_id')
            ->where('settlement_status', '!=', 'unsettled')
            ->update(['settlement_status' => 'unsettled']);
        
        echo "已修复 {$fixed} 条不一致的记录\n";
    }
    
    // 统计当前状态
    echo "\n===== 当前统计 =====\n";
    
    $unsettledCount = DB::table('transactions')
        ->where('settlement_status', 'unsettled')
        ->count();
    
    $settledCount = DB::table('transactions')
        ->where('settlement_status', 'settled')
        ->count();
    
    $totalCount = DB::table('transactions')->count();
    
    echo "未结算记录: {$unsettledCount}\n";
    echo "已结算记录: {$settledCount}\n";
    echo "总记录数: {$totalCount}\n";
    
    // 验证数据完整性
    if ($unsettledCount + $settledCount === $totalCount) {
        echo "\n✓ 数据完整性验证通过\n";
    } else {
        echo "\n✗ 警告: 数据完整性验证失败,存在 settlement_status 不是 'unsettled' 或 'settled' 的记录\n";
        
        $otherCount = $totalCount - $unsettledCount - $settledCount;
        echo "异常记录数: {$otherCount}\n";
    }
    
    echo "\n修复完成!\n";
    
} catch (\Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

