-- 财务管理系统数据库设计
-- 基于需求文档的完整数据库表结构

-- 1. 用户表 (后台管理员和财务人员)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE COMMENT '用户名',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    role ENUM('admin', 'finance') NOT NULL DEFAULT 'finance' COMMENT '角色：管理员/财务人员',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
    last_login_at TIMESTAMP NULL COMMENT '最后登录时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT '后台用户表';

-- 2. 外勤人员表 (前台用户)
CREATE TABLE field_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE COMMENT '账号',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    name VARCHAR(100) NOT NULL COMMENT '姓名',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '状态',
    last_login_at TIMESTAMP NULL COMMENT '最后登录时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT '外勤人员表';

-- 3. 支付渠道表
CREATE TABLE channels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '渠道名称',
    code VARCHAR(50) NOT NULL UNIQUE COMMENT '渠道代码',
    label VARCHAR(100) NULL COMMENT '标签（线上/线下/第三方）',
    category ENUM('bank', 'ewallet', 'cash', 'other') NOT NULL DEFAULT 'other' COMMENT '分类',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT '启用状态',
    transaction_count INT UNSIGNED DEFAULT 0 COMMENT '累计交易次数',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT '支付渠道表';

-- 4. 渠道余额表 (每日初始余额记录)
CREATE TABLE channel_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL COMMENT '渠道ID',
    date DATE NOT NULL COMMENT '日期',
    rmb_initial_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '人民币初始金额',
    hkd_initial_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '港币初始金额',
    rmb_final_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '人民币结束金额',
    hkd_final_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT '港币结束金额',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_channel_date (channel_id, date)
) COMMENT '渠道余额表';

-- 5. 余额调整记录表
CREATE TABLE balance_adjustments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id BIGINT UNSIGNED NOT NULL COMMENT '渠道ID',
    currency ENUM('rmb', 'hkd') NOT NULL COMMENT '货币类型',
    adjustment_amount DECIMAL(15,2) NOT NULL COMMENT '调整金额（正数增加，负数减少）',
    reason VARCHAR(500) NOT NULL COMMENT '调整原因',
    operator_id BIGINT UNSIGNED NOT NULL COMMENT '操作员ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES users(id)
) COMMENT '余额调整记录表';

-- 6. 交易记录表
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE COMMENT '唯一标识符（幂等性保证）',
    user_id BIGINT UNSIGNED NOT NULL COMMENT '外勤人员ID',
    type ENUM('income', 'outcome', 'exchange') NOT NULL COMMENT '交易类型：入账/出账/兑换',
    rmb_amount DECIMAL(15,2) NOT NULL COMMENT '人民币金额',
    hkd_amount DECIMAL(15,2) NOT NULL COMMENT '港币金额',
    exchange_rate DECIMAL(10,5) NOT NULL COMMENT '交易汇率',
    instant_rate DECIMAL(10,5) NULL COMMENT '即时汇率（兑换交易专用）',
    channel_id BIGINT UNSIGNED NOT NULL COMMENT '支付渠道ID',
    location VARCHAR(200) NULL COMMENT '交易地点',
    notes TEXT NULL COMMENT '备注',
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'success' COMMENT '状态',
    submit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES field_users(id),
    FOREIGN KEY (channel_id) REFERENCES channels(id),
    INDEX idx_user_time (user_id, created_at),
    INDEX idx_channel_time (channel_id, created_at),
    INDEX idx_type_time (type, created_at)
) COMMENT '交易记录表';

-- 7. 交易草稿表
CREATE TABLE transaction_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE COMMENT '唯一标识符',
    user_id BIGINT UNSIGNED NOT NULL COMMENT '外勤人员ID',
    type ENUM('income', 'outcome', 'exchange') NOT NULL COMMENT '交易类型',
    rmb_amount DECIMAL(15,2) NULL COMMENT '人民币金额',
    hkd_amount DECIMAL(15,2) NULL COMMENT '港币金额',
    exchange_rate DECIMAL(10,5) NULL COMMENT '交易汇率',
    instant_rate DECIMAL(10,5) NULL COMMENT '即时汇率',
    channel_id BIGINT UNSIGNED NULL COMMENT '支付渠道ID',
    location VARCHAR(200) NULL COMMENT '交易地点',
    notes TEXT NULL COMMENT '备注',
    last_modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后修改时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES field_users(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id),
    INDEX idx_user_modified (user_id, last_modified)
) COMMENT '交易草稿表';

-- 8. 图片存储表
CREATE TABLE images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE COMMENT '图片唯一标识',
    transaction_id BIGINT UNSIGNED NULL COMMENT '关联交易ID',
    draft_id BIGINT UNSIGNED NULL COMMENT '关联草稿ID',
    original_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_size INT UNSIGNED NOT NULL COMMENT '文件大小（字节）',
    mime_type VARCHAR(100) NOT NULL COMMENT 'MIME类型',
    width INT UNSIGNED NULL COMMENT '图片宽度',
    height INT UNSIGNED NULL COMMENT '图片高度',
    file_content LONGBLOB NOT NULL COMMENT '图片二进制内容',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (draft_id) REFERENCES transaction_drafts(id) ON DELETE CASCADE,
    INDEX idx_transaction (transaction_id),
    INDEX idx_draft (draft_id)
) COMMENT '图片存储表';

-- 9. 系统配置表
CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE COMMENT '配置项名称',
    key_value TEXT NOT NULL COMMENT '配置值',
    description VARCHAR(500) NULL COMMENT '描述',
    type ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string' COMMENT '值类型',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT '系统配置表';

-- 10. 操作日志表
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL COMMENT '操作用户ID',
    user_type ENUM('admin', 'field') NOT NULL COMMENT '用户类型',
    action VARCHAR(100) NOT NULL COMMENT '操作类型',
    model VARCHAR(100) NOT NULL COMMENT '操作模型',
    model_id BIGINT UNSIGNED NULL COMMENT '操作记录ID',
    old_values JSON NULL COMMENT '修改前数据',
    new_values JSON NULL COMMENT '修改后数据',
    ip_address VARCHAR(45) NULL COMMENT 'IP地址',
    user_agent TEXT NULL COMMENT '用户代理',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) COMMENT '操作日志表';

-- 11. Token管理表
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
) COMMENT 'API Token表';

-- 初始化系统配置数据
INSERT INTO settings (key_name, key_value, description, type) VALUES
('access_token_expire', '30', 'Access Token过期时间（分钟）', 'number'),
('refresh_token_expire', '10080', 'Refresh Token过期时间（分钟，7天）', 'number'),
('auto_logout_time', '15', '自动登出时间（分钟）', 'number'),
('image_max_size', '5242880', '图片最大尺寸（字节，5MB）', 'number'),
('image_quality', '80', '图片压缩质量（1-100）', 'number'),
('image_formats', '["jpg","jpeg","png","gif"]', '允许的图片格式', 'json'),
('exchange_rate_precision', '5', '汇率精度（小数位数）', 'number'),
('log_retention_days', '90', '日志保存天数', 'number');

-- 初始化支付渠道数据
INSERT INTO channels (name, code, label, category, status) VALUES
('中国银行', 'BOC', '银行', 'bank', 'active'),
('工商银行', 'ICBC', '银行', 'bank', 'active'),
('建设银行', 'CCB', '银行', 'bank', 'active'),
('支付宝', 'ALIPAY', '第三方', 'ewallet', 'active'),
('微信支付', 'WECHAT', '第三方', 'ewallet', 'active'),
('现金', 'CASH', '线下', 'cash', 'active');

-- 创建管理员账户 (密码: admin123)
INSERT INTO users (username, password, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
