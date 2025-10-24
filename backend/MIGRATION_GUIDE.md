# 数据库迁移指南

## 新增功能说明
本次更新为结余功能添加了以下新特性：
1. 每天最多结余一次限制
2. 密码验证机制
3. 即时买断利润单独计算
4. 结余日期记录

## 迁移文件列表

### 1. `2025_10_24_000001_update_settlements_add_date_fields.php`
添加结余日期相关字段：
- `settlement_date`：结余日期（YYYY-MM-DD），添加唯一索引
- `created_by`：执行结余的用户ID

### 2. `2025_10_24_000002_add_settlement_password_to_settings.php`
添加结余确认密码配置：
- 默认密码：`123456`
- 密码使用 PHP 的 `password_hash` 加密存储

### 3. `2025_10_24_000003_add_profit_breakdown_to_settlements.php`
添加利润明细字段：
- `outgoing_profit`：出账利润
- `instant_profit`：即时买断利润
- `instant_buyout_rate`：即时买断汇率

## 执行迁移

在后端目录执行：
```bash
cd CurrencyExSystem\ExchangeSystem\backend
php artisan migrate
```

## 回滚迁移

如果需要回滚：
```bash
php artisan migrate:rollback --step=3
```

## 验证迁移

检查数据库表结构：
```sql
-- 查看 settlements 表结构
DESC settlements;

-- 查看 settings 表中的密码配置
SELECT * FROM settings WHERE key_name = 'settlement_password';
```

## 注意事项

1. **唯一索引**：`settlement_date` 字段有唯一索引，确保每天最多一条结余记录
2. **默认密码**：首次部署后请立即修改默认密码 `123456`
3. **字段兼容性**：新增字段都设置了默认值，不影响现有数据
4. **数据备份**：执行迁移前请备份数据库

## 密码管理

修改结余确认密码（在系统设置中）：
```php
Setting::set('settlement_password', password_hash('新密码', PASSWORD_DEFAULT), '结余确认密码', 'password');
```

