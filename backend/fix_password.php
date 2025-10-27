<?php

$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "=== 密码哈希修复 ===\n\n";
echo "原始密码: {$password}\n";
echo "哈希值: {$hash}\n\n";
echo "请在数据库中执行以下 SQL:\n\n";
echo "UPDATE settings\n";
echo "SET key_value = '{$hash}'\n";
echo "WHERE key_name = 'settlement_password';\n\n";
echo "或者直接将 key_value 字段的值改为:\n";
echo "{$hash}\n";

