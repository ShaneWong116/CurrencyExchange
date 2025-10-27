<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== 检查数据库密码状态 ===\n\n";

try {
    $setting = DB::table('settings')
        ->where('key_name', 'settlement_password')
        ->first();
    
    if (!$setting) {
        echo "❌ 数据库中不存在 settlement_password 记录\n";
        exit(1);
    }
    
    echo "✓ 找到密码记录:\n";
    echo "  ID: {$setting->id}\n";
    echo "  key_name: {$setting->key_name}\n";
    echo "  key_value: {$setting->key_value}\n";
    echo "  type: {$setting->type}\n";
    echo "  长度: " . strlen($setting->key_value) . " 字符\n\n";
    
    // 判断是明文还是哈希
    if (strlen($setting->key_value) < 50) {
        echo "⚠️  这是明文密码!\n";
        echo "  当前值: {$setting->key_value}\n\n";
        
        echo "需要执行以下操作:\n";
        $hash = password_hash('123456', PASSWORD_DEFAULT);
        echo "\n复制这个 SQL 到数据库执行:\n\n";
        echo "UPDATE settings\n";
        echo "SET key_value = '{$hash}'\n";
        echo "WHERE key_name = 'settlement_password';\n\n";
    } else {
        echo "✓ 这是哈希密码\n\n";
        
        // 测试密码
        $testPasswords = ['123456', '654321', '888888'];
        echo "测试常用密码:\n";
        foreach ($testPasswords as $pwd) {
            $result = password_verify($pwd, $setting->key_value);
            echo "  {$pwd}: " . ($result ? "✅ 匹配" : "❌ 不匹配") . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n=== 完成 ===\n";

