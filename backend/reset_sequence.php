<?php

/**
 * 重置数据库表的自增序列
 * 
 * ⚠️ 警告: 此脚本会重置表的自增ID序列
 * 仅在开发/测试环境使用,生产环境请谨慎!
 * 
 * 使用方法:
 * php reset_sequence.php                           # 查看所有表
 * php reset_sequence.php <表名>                    # 智能重置(有数据则从最大ID开始)
 * php reset_sequence.php <表名> --force            # 强制从1开始(⚠️ 危险!)
 * 
 * 示例:
 * php reset_sequence.php channels                  # 从最大ID开始
 * php reset_sequence.php channels --force          # 强制从1开始
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// 获取命令行参数指定的表名,如果没有则显示所有表
$targetTable = $argv[1] ?? null;
$forceReset = in_array('--force', $argv) || in_array('-f', $argv);

// 获取数据库类型
$driver = DB::connection()->getDriverName();

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  数据库自增序列重置工具\n";
echo "  数据库类型: {$driver}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($driver === 'sqlite') {
    // SQLite 的处理方式
    
    if ($targetTable) {
        // 重置指定表
        resetSqliteSequence($targetTable, $forceReset);
    } else {
        // 显示所有表的序列信息
        echo "当前所有表的自增序列状态:\n\n";
        $sequences = DB::select("SELECT name, seq FROM sqlite_sequence ORDER BY name");
        
        if (empty($sequences)) {
            echo "  没有找到任何自增序列记录。\n\n";
        } else {
            printf("  %-30s %s\n", "表名", "当前序列值");
            echo "  " . str_repeat("-", 50) . "\n";
            foreach ($sequences as $seq) {
                printf("  %-30s %d\n", $seq->name, $seq->seq);
            }
            echo "\n";
            echo "提示: 使用 'php reset_sequence.php <表名>' 来重置指定表的序列\n";
            echo "例如: php reset_sequence.php balance_adjustments\n\n";
        }
    }
    
} elseif ($driver === 'mysql') {
    // MySQL 的处理方式
    
    if ($targetTable) {
        resetMysqlSequence($targetTable, $forceReset);
    } else {
        // 显示所有表的自增值
        echo "当前所有表的自增序列状态:\n\n";
        $database = DB::getDatabaseName();
        $tables = DB::select("
            SELECT TABLE_NAME, AUTO_INCREMENT 
            FROM information_schema.tables 
            WHERE table_schema = ? 
            AND AUTO_INCREMENT IS NOT NULL
            ORDER BY TABLE_NAME
        ", [$database]);
        
        if (empty($tables)) {
            echo "  没有找到任何自增表。\n\n";
        } else {
            printf("  %-30s %s\n", "表名", "当前AUTO_INCREMENT值");
            echo "  " . str_repeat("-", 50) . "\n";
            foreach ($tables as $table) {
                printf("  %-30s %d\n", $table->TABLE_NAME, $table->AUTO_INCREMENT ?? 1);
            }
            echo "\n";
            echo "提示: 使用 'php reset_sequence.php <表名>' 来重置指定表的序列\n\n";
        }
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

/**
 * 重置 SQLite 表的序列
 */
function resetSqliteSequence(string $tableName, bool $forceReset = false): void
{
    echo "准备重置表: {$tableName}\n";
    if ($forceReset) {
        echo "🔥 强制重置模式: 将序列强制设为 0 (下次插入从 1 开始)\n";
    }
    echo "\n";
    
    // 检查表是否存在
    $tableExists = DB::select("
        SELECT name FROM sqlite_master 
        WHERE type='table' AND name=?
    ", [$tableName]);
    
    if (empty($tableExists)) {
        echo "❌ 错误: 表 '{$tableName}' 不存在!\n\n";
        return;
    }
    
    // 获取当前序列值
    $currentSeq = DB::select("SELECT seq FROM sqlite_sequence WHERE name=?", [$tableName]);
    $currentValue = $currentSeq[0]->seq ?? 0;
    
    // 获取表中的记录数
    $count = DB::table($tableName)->count();
    
    // 获取当前最大ID
    $maxId = DB::table($tableName)->max('id') ?? 0;
    
    echo "当前状态:\n";
    echo "  序列值: {$currentValue}\n";
    echo "  记录数: {$count}\n";
    echo "  最大ID: {$maxId}\n\n";
    
    if ($forceReset) {
        // 强制重置为 0 (下次插入将从 1 开始)
        echo "⚠️  【强制模式】将序列重置为 0...\n";
        echo "⚠️  警告: 如果表中已有数据,可能导致ID冲突!\n";
        
        // 如果表中有数据,显示警告
        if ($count > 0) {
            echo "\n";
            echo "╔═══════════════════════════════════════╗\n";
            echo "║  ⚠️  危险操作警告                     ║\n";
            echo "║  表中当前有 {$count} 条记录              ║\n";
            echo "║  最大ID为 {$maxId}                      ║\n";
            echo "║  强制重置可能导致ID冲突!              ║\n";
            echo "╚═══════════════════════════════════════╝\n";
            echo "\n";
        }
        
        // 执行强制重置
        if ($currentSeq) {
            DB::update("UPDATE sqlite_sequence SET seq=0 WHERE name=?", [$tableName]);
        } else {
            DB::insert("INSERT INTO sqlite_sequence (name, seq) VALUES (?, 0)", [$tableName]);
        }
        
        echo "✅ 序列已强制重置为 0,下次插入将从 1 开始\n\n";
        
    } elseif ($count === 0) {
        // 表为空,完全删除序列记录
        echo "⚠️  表为空,将删除序列记录...\n";
        DB::delete("DELETE FROM sqlite_sequence WHERE name=?", [$tableName]);
        echo "✅ 序列已删除,下次插入将从 1 开始\n\n";
        
    } else {
        // 表有数据,将序列重置为最大ID (安全模式)
        echo "⚠️  表中有数据,将序列重置为当前最大ID ({$maxId})...\n";
        DB::update("UPDATE sqlite_sequence SET seq=? WHERE name=?", [$maxId, $tableName]);
        echo "✅ 序列已重置为 {$maxId},下次插入将从 " . ($maxId + 1) . " 开始\n\n";
        echo "💡 提示: 如果想强制从 1 开始,请使用 --force 参数\n";
        echo "   命令: php reset_sequence.php {$tableName} --force\n\n";
    }
}

/**
 * 重置 MySQL 表的序列
 */
function resetMysqlSequence(string $tableName, bool $forceReset = false): void
{
    echo "准备重置表: {$tableName}\n";
    if ($forceReset) {
        echo "🔥 强制重置模式: 将AUTO_INCREMENT强制设为 1\n";
    }
    echo "\n";
    
    // 检查表是否存在
    $tableExists = DB::select("SHOW TABLES LIKE ?", [$tableName]);
    
    if (empty($tableExists)) {
        echo "❌ 错误: 表 '{$tableName}' 不存在!\n\n";
        return;
    }
    
    // 获取当前AUTO_INCREMENT值
    $database = DB::getDatabaseName();
    $tableStatus = DB::select("
        SELECT AUTO_INCREMENT 
        FROM information_schema.tables 
        WHERE table_schema=? AND table_name=?
    ", [$database, $tableName]);
    
    $currentValue = $tableStatus[0]->AUTO_INCREMENT ?? 1;
    
    // 获取表中的记录数
    $count = DB::table($tableName)->count();
    
    // 获取当前最大ID
    $maxId = DB::table($tableName)->max('id') ?? 0;
    
    echo "当前状态:\n";
    echo "  AUTO_INCREMENT值: {$currentValue}\n";
    echo "  记录数: {$count}\n";
    echo "  最大ID: {$maxId}\n\n";
    
    if ($forceReset) {
        // 强制重置为 1
        echo "⚠️  【强制模式】将 AUTO_INCREMENT 重置为 1...\n";
        echo "⚠️  警告: 如果表中已有数据,可能导致ID冲突!\n";
        
        // 如果表中有数据,显示警告
        if ($count > 0) {
            echo "\n";
            echo "╔═══════════════════════════════════════╗\n";
            echo "║  ⚠️  危险操作警告                     ║\n";
            echo "║  表中当前有 {$count} 条记录              ║\n";
            echo "║  最大ID为 {$maxId}                      ║\n";
            echo "║  强制重置可能导致ID冲突!              ║\n";
            echo "╚═══════════════════════════════════════╝\n";
            echo "\n";
        }
        
        DB::statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1");
        echo "✅ AUTO_INCREMENT 已强制重置为 1\n\n";
        
    } elseif ($count === 0) {
        // 表为空,重置为1
        echo "⚠️  表为空,将重置序列为 1...\n";
        DB::statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1");
        echo "✅ AUTO_INCREMENT 已重置为 1\n\n";
        
    } else {
        // 表有数据,重置为最大ID+1 (安全模式)
        $newValue = $maxId + 1;
        echo "⚠️  表中有数据,将 AUTO_INCREMENT 重置为 {$newValue}...\n";
        DB::statement("ALTER TABLE {$tableName} AUTO_INCREMENT = {$newValue}");
        echo "✅ AUTO_INCREMENT 已重置为 {$newValue}\n\n";
        echo "💡 提示: 如果想强制从 1 开始,请使用 --force 参数\n";
        echo "   命令: php reset_sequence.php {$tableName} --force\n\n";
    }
}


