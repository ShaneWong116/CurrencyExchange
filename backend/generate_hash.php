<?php
// 生成密码哈希
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "密码: {$password}\n";
echo "哈希: {$hash}\n";
echo "\n";
echo "SQL 语句:\n";
echo "DELETE FROM settings WHERE key_name = 'settlement_password';\n";
echo "INSERT INTO settings (key_name, key_value, description, type, created_at, updated_at)\n";
echo "VALUES ('settlement_password', '{$hash}', '结余确认密码(哈希加密)', 'string', datetime('now'), datetime('now'));\n";

