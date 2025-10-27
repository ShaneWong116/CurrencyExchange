<?php
// 快速生成密码哈希
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "=== 复制下面的 SQL 语句 ===\n\n";
echo "DELETE FROM settings WHERE key_name = 'settlement_password';\n\n";
echo "INSERT INTO settings (key_name, key_value, description, type, created_at, updated_at)\n";
echo "VALUES (\n";
echo "    'settlement_password',\n";
echo "    '{$hash}',\n";
echo "    '结余确认密码(哈希加密)',\n";
echo "    'string',\n";
echo "    datetime('now'),\n";
echo "    datetime('now')\n";
echo ");\n";

