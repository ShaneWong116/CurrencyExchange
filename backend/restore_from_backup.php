<?php

/*
 * 从旧的SQLite备份文件恢复数据到新数据库
 * 
 * 用法: php restore_from_backup.php <备份文件路径>
 * 例如: php restore_from_backup.php ../database_backup_old.sqlite
 * 
 * 功能:
 * 1. 分析备份数据库和当前数据库的结构差异
 * 2. 智能映射字段
 * 3. 恢复数据到当前数据库
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// 获取备份文件路径
if ($argc < 2) {
    echo "用法: php restore_from_backup.php <备份文件路径>\n";
    echo "例如: php restore_from_backup.php ../database_backup.sqlite\n";
    exit(1);
}

$backupFile = $argv[1];

if (!file_exists($backupFile)) {
    echo "❌ 错误: 备份文件不存在: $backupFile\n";
    exit(1);
}

echo "=== 数据恢复工具 ===\n";
echo "备份文件: $backupFile\n\n";

try {
    // 连接到备份数据库
    $backupDb = new PDO('sqlite:' . $backupFile);
    $backupDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. 分析备份数据库结构...\n";
    
    // 获取备份数据库的transactions表结构
    $backupColumns = [];
    $stmt = $backupDb->query("PRAGMA table_info(transactions)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $backupColumns[$row['name']] = $row;
        echo "   - {$row['name']} ({$row['type']})\n";
    }
    
    echo "\n2. 分析当前数据库结构...\n";
    
    // 获取当前数据库的transactions表结构
    $currentColumns = [];
    $stmt = DB::connection()->getPdo()->query("PRAGMA table_info(transactions)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentColumns[$row['name']] = $row;
        echo "   - {$row['name']} ({$row['type']})\n";
    }
    
    echo "\n3. 字段映射分析...\n";
    
    // 找出共同字段
    $commonFields = array_intersect(array_keys($backupColumns), array_keys($currentColumns));
    echo "   共同字段: " . implode(', ', $commonFields) . "\n";
    
    // 找出备份数据库独有的字段
    $backupOnlyFields = array_diff(array_keys($backupColumns), array_keys($currentColumns));
    if (!empty($backupOnlyFields)) {
        echo "   ⚠️  备份数据库独有字段(将被忽略): " . implode(', ', $backupOnlyFields) . "\n";
    }
    
    // 找出当前数据库独有的字段
    $currentOnlyFields = array_diff(array_keys($currentColumns), array_keys($backupColumns));
    if (!empty($currentOnlyFields)) {
        echo "   ℹ️  当前数据库新增字段(将使用默认值): " . implode(', ', $currentOnlyFields) . "\n";
    }
    
    echo "\n4. 统计备份数据...\n";
    
    // 统计各类数据量
    $tables = ['transactions', 'channels', 'field_users', 'locations'];
    $stats = [];
    
    foreach ($tables as $table) {
        try {
            $stmt = $backupDb->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $stats[$table] = $count;
            echo "   - $table: $count 条记录\n";
        } catch (Exception $e) {
            echo "   - $table: 表不存在或无法访问\n";
            $stats[$table] = 0;
        }
    }
    
    echo "\n5. 确认恢复操作\n";
    echo "   ⚠️  警告: 此操作将清空当前数据库的以下表并恢复备份数据:\n";
    foreach ($stats as $table => $count) {
        if ($count > 0) {
            echo "      - $table ($count 条记录)\n";
        }
    }
    echo "\n";
    echo "   是否继续? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "   操作已取消。\n";
        exit(0);
    }
    
    echo "\n6. 开始恢复数据...\n";
    
    DB::beginTransaction();
    DB::statement('PRAGMA foreign_keys = OFF');
    
    // 恢复顺序: 先恢复基础表,再恢复关联表
    $restoreOrder = [
        'field_users' => 'users',  // 可能的表名变化
        'channels' => 'channels',
        'locations' => 'locations',
        'transactions' => 'transactions',
    ];
    
    foreach ($restoreOrder as $backupTable => $currentTable) {
        if ($stats[$backupTable] > 0) {
            echo "   恢复 $currentTable...\n";
            
            // 检查当前数据库是否有该表
            try {
                $stmt = DB::connection()->getPdo()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$currentTable'");
                $tableExists = $stmt->fetch();
                
                if (!$tableExists) {
                    echo "      ⚠️  跳过: 当前数据库中不存在 $currentTable 表\n";
                    continue;
                }
                
                // 获取备份表的列
                $stmt = $backupDb->query("PRAGMA table_info($backupTable)");
                $backupTableColumns = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $backupTableColumns[] = $row['name'];
                }
                
                // 获取当前表的列
                $stmt = DB::connection()->getPdo()->query("PRAGMA table_info($currentTable)");
                $currentTableColumns = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $currentTableColumns[] = $row['name'];
                }
                
                // 找出共同列
                $commonColumns = array_intersect($backupTableColumns, $currentTableColumns);
                
                if (empty($commonColumns)) {
                    echo "      ⚠️  跳过: 没有共同字段\n";
                    continue;
                }
                
                // 清空当前表
                DB::table($currentTable)->truncate();
                
                // 读取备份数据
                $stmt = $backupDb->query("SELECT * FROM $backupTable");
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $imported = 0;
                $skipped = 0;
                
                foreach ($records as $record) {
                    try {
                        // 只插入共同字段的数据
                        $data = [];
                        foreach ($commonColumns as $col) {
                            if (array_key_exists($col, $record)) {
                                $data[$col] = $record[$col];
                            }
                        }
                        
                        // 特殊处理: transactions表的type字段
                        if ($currentTable === 'transactions' && isset($data['type'])) {
                            // 如果旧数据库没有instant_buyout类型,保持原有类型
                            // 不需要特殊处理
                        }
                        
                        // 特殊处理: 添加当前数据库新增的必填字段默认值
                        if ($currentTable === 'transactions') {
                            if (!isset($data['settlement_status'])) {
                                $data['settlement_status'] = 'unsettled';
                            }
                            if (!isset($data['status'])) {
                                $data['status'] = 'success';
                            }
                            // instant_profit 字段：旧数据为NULL，新录入的即时买断交易会自动计算
                            if (!isset($data['instant_profit'])) {
                                $data['instant_profit'] = null;
                            }
                        }
                        
                        DB::table($currentTable)->insert($data);
                        $imported++;
                        
                    } catch (Exception $e) {
                        $skipped++;
                        echo "      ⚠️  跳过记录 ID {$record['id']}: {$e->getMessage()}\n";
                    }
                }
                
                echo "      ✅ 完成: 导入 $imported 条, 跳过 $skipped 条\n";
                
            } catch (Exception $e) {
                echo "      ❌ 错误: {$e->getMessage()}\n";
            }
        }
    }
    
    DB::statement('PRAGMA foreign_keys = ON');
    DB::commit();
    
    echo "\n✅ 数据恢复完成!\n";
    echo "\n请检查数据是否正确,特别是:\n";
    echo "  - 交易记录的数量和内容\n";
    echo "  - 渠道余额是否正确\n";
    echo "  - 外勤人员信息是否完整\n";
    
} catch (Exception $e) {
    if (isset($backupDb)) {
        DB::rollBack();
    }
    echo "\n❌ 恢复失败: " . $e->getMessage() . "\n";
    echo "错误详情:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

