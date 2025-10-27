-- 初始化结余密码为 123456
-- 密码哈希使用 PHP password_hash('123456', PASSWORD_DEFAULT) 生成

-- 删除旧记录(如果存在)
DELETE FROM settings WHERE key_name = 'settlement_password';

-- 插入新密码记录
-- 注意: 这个哈希值是用 password_hash('123456', PASSWORD_DEFAULT) 生成的
-- 由于每次生成的哈希值不同,您需要运行 PHP 代码来生成正确的哈希
INSERT INTO settings (key_name, key_value, description, type, created_at, updated_at)
VALUES (
    'settlement_password',
    '$2y$12$LKEjVhQBzT2zZ7Z8yL8N0.xvN5k5rV3qXYJKL8xHJ0pZ9qN5kN5kN',  -- 这只是示例,需要替换
    '结余确认密码(哈希加密)',
    'string',
    datetime('now'),
    datetime('now')
);

-- 验证插入
SELECT * FROM settings WHERE key_name = 'settlement_password';

