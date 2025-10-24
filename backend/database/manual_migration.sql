-- ============================================================
-- 结余功能新增特性 - 手动迁移SQL脚本
-- 创建日期: 2025-10-24
-- 说明: 如果无法使用 php artisan migrate，可直接执行此SQL
-- ============================================================

-- 检查数据库连接
SELECT 'Database connection OK' AS status;

-- ============================================================
-- 迁移 1: 添加结余日期和用户字段
-- 文件: 2025_10_24_000001_update_settlements_add_date_fields.php
-- ============================================================

-- 添加结余日期字段
ALTER TABLE settlements 
ADD COLUMN settlement_date DATE NOT NULL COMMENT '结余日期(YYYY-MM-DD)' AFTER id;

-- 添加执行结余的用户ID
ALTER TABLE settlements 
ADD COLUMN created_by BIGINT UNSIGNED NULL COMMENT '执行结余的用户ID' AFTER notes;

-- 添加唯一索引（确保每天最多一条记录）
ALTER TABLE settlements 
ADD UNIQUE INDEX unique_settlement_date (settlement_date);

SELECT '✓ 迁移1完成: 添加结余日期和用户字段' AS status;

-- ============================================================
-- 迁移 2: 添加结余确认密码配置
-- 文件: 2025_10_24_000002_add_settlement_password_to_settings.php
-- ============================================================

-- 插入结余确认密码配置
-- 默认密码: 123456
-- 哈希值: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO settings (key_name, key_value, description, type, created_at, updated_at)
VALUES (
    'settlement_password', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    '结余确认密码（用于二次验证）', 
    'password', 
    NOW(), 
    NOW()
);

SELECT '✓ 迁移2完成: 添加结余确认密码配置（默认密码: 123456）' AS status;

-- ============================================================
-- 迁移 3: 添加利润明细字段
-- 文件: 2025_10_24_000003_add_profit_breakdown_to_settlements.php
-- ============================================================

-- 添加出账利润字段
ALTER TABLE settlements
ADD COLUMN outgoing_profit DECIMAL(15,3) DEFAULT 0 NOT NULL COMMENT '出账利润(HKD)' AFTER profit;

-- 添加即时买断利润字段
ALTER TABLE settlements
ADD COLUMN instant_profit DECIMAL(15,3) DEFAULT 0 NOT NULL COMMENT '即时买断利润(HKD)' AFTER outgoing_profit;

-- 添加即时买断汇率字段
ALTER TABLE settlements
ADD COLUMN instant_buyout_rate DECIMAL(10,5) NULL COMMENT '即时买断汇率(CNY/HKD)' AFTER instant_profit;

SELECT '✓ 迁移3完成: 添加利润明细字段' AS status;

-- ============================================================
-- 验证迁移结果
-- ============================================================

-- 查看 settlements 表结构
SELECT '--- Settlements 表结构 ---' AS info;
DESC settlements;

-- 查看索引
SELECT '--- Settlements 表索引 ---' AS info;
SHOW INDEX FROM settlements;

-- 查看密码配置
SELECT '--- 结余密码配置 ---' AS info;
SELECT key_name, description, type, created_at 
FROM settings 
WHERE key_name = 'settlement_password';

-- ============================================================
-- 完成
-- ============================================================

SELECT '
============================================================
✓ 所有迁移已完成！

新增字段:
  - settlements.settlement_date (唯一索引)
  - settlements.created_by
  - settlements.outgoing_profit
  - settlements.instant_profit
  - settlements.instant_buyout_rate

新增配置:
  - settings.settlement_password (默认密码: 123456)

⚠️ 重要提醒:
  1. 请立即修改默认密码 123456
  2. 清除后端缓存: php artisan cache:clear
  3. 测试API端点是否正常工作

下一步:
  - 前端构建: cd frontend && npm run build
  - 测试功能: 访问 /settlement/preview

============================================================
' AS completion_message;

-- ============================================================
-- 回滚脚本（如需要）
-- ============================================================
-- 
-- -- 回滚迁移3
-- ALTER TABLE settlements DROP COLUMN instant_buyout_rate;
-- ALTER TABLE settlements DROP COLUMN instant_profit;
-- ALTER TABLE settlements DROP COLUMN outgoing_profit;
-- 
-- -- 回滚迁移2
-- DELETE FROM settings WHERE key_name = 'settlement_password';
-- 
-- -- 回滚迁移1
-- ALTER TABLE settlements DROP INDEX unique_settlement_date;
-- ALTER TABLE settlements DROP COLUMN created_by;
-- ALTER TABLE settlements DROP COLUMN settlement_date;
-- 
-- ============================================================

