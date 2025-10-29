# 余额调整功能合并说明

## 概述

本次更新将原本分散的三个调整功能（本金调整、渠道余额调整、港币余额调整）合并到统一的 **余额调整** 模块中，使系统更加清晰易用。

## 主要变更

### 1. 数据库结构变更

在 `balance_adjustments` 表中新增字段：

- `adjustment_category` - 调整分类（capital=本金, channel=渠道余额, hkd_balance=港币余额）
- `settlement_id` - 关联结算ID（可空，用于本金和港币余额的结算调整）
- 将 `channel_id` 改为可空（本金和港币余额调整不需要渠道）

### 2. 模型更新

#### BalanceAdjustment 模型新增功能

**新增作用域：**
- `capital()` - 筛选本金调整记录
- `channel()` - 筛选渠道余额调整记录  
- `hkdBalance()` - 筛选港币余额调整记录

**新增静态方法：**
- `getCurrentCapital()` - 获取当前系统本金
- `getCurrentHkdBalance()` - 获取当前港币余额
- `createCapitalAdjustment()` - 创建本金调整记录
- `createHkdBalanceAdjustment()` - 创建港币余额调整记录

**新增关联：**
- `settlement()` - 关联结算记录

### 3. 资源整合

#### BalanceAdjustmentResource 更新

**表单改进：**
- 新增"调整分类"选择器，支持三种类型：本金、渠道余额、港币余额
- 根据选择的分类动态显示不同的表单字段
- 本金和港币余额调整时显示当前值
- 渠道余额调整时显示渠道选择和货币类型

**列表改进：**
- 新增"调整分类"列，使用彩色徽章区分不同类型
- 新增"关联结算"列，显示结算触发的调整
- 优化筛选器，新增分类筛选

**创建逻辑优化：**
- 根据调整分类执行不同的后续操作
- 渠道余额调整：更新 ChannelBalance
- 港币余额调整：同步更新系统设置
- 本金调整：无需额外操作（直接从调整记录获取）

### 4. 仪表盘更新

#### BalanceOverview Widget

**链接优化：**
- 本金卡片 → 跳转至余额调整页面并自动筛选"本金"类型
- 人民币余额卡片 → 跳转至余额调整页面并自动筛选"渠道余额"类型  
- 港币余额卡片 → 跳转至余额调整页面并自动筛选"港币余额"类型

**数据源更新：**
- 使用 `BalanceAdjustment::getCurrentCapital()` 获取本金
- 使用 `BalanceAdjustment::getCurrentHkdBalance()` 获取港币余额

### 5. 服务层更新

#### SettlementService

- 使用 `BalanceAdjustment::getCurrentCapital()` 替代 `CapitalAdjustment::getCurrentCapital()`
- 使用 `BalanceAdjustment::getCurrentHkdBalance()` 替代从设置中读取
- 使用 `BalanceAdjustment::createCapitalAdjustment()` 创建本金调整记录
- 使用 `BalanceAdjustment::createHkdBalanceAdjustment()` 创建港币余额调整记录

### 6. 模型关联更新

#### Settlement 模型

- 新增 `balanceAdjustments()` 关联，返回所有余额调整记录
- 保留 `capitalAdjustments()` 方法以向后兼容，通过 `balanceAdjustments()->where('adjustment_category', 'capital')` 实现

### 7. 文件删除

已删除以下不再需要的文件：
- `app/Filament/Resources/CapitalAdjustmentResource.php`
- `app/Filament/Resources/CapitalAdjustmentResource/Pages/` 目录及其所有文件

**保留的文件（用于向后兼容）：**
- `app/Models/CapitalAdjustment.php`
- `app/Models/HkdBalanceAdjustment.php`

## 使用说明

### 创建余额调整

1. 访问 **财务管理 → 余额调整**
2. 点击"新建"按钮
3. 选择调整类型（本金/渠道余额/港币余额）
4. 根据选择的类型填写相应信息
5. 保存即可

### 查看调整记录

- 在余额调整列表页面，可以通过"调整分类"筛选器快速筛选特定类型的调整记录
- 从仪表盘卡片点击，会自动应用相应的筛选

### 数据迁移

如果系统中已有 `capital_adjustments` 或 `hkd_balance_adjustments` 表的数据，需要运行数据迁移脚本将数据合并到 `balance_adjustments` 表中。

## 向后兼容性

- 保留了 `CapitalAdjustment` 和 `HkdBalanceAdjustment` 模型，现有代码仍可使用
- `Settlement::capitalAdjustments()` 关联仍然可用
- 建议逐步迁移到新的 `BalanceAdjustment` 模型

## 优势

1. **界面统一**：所有余额相关的调整都在一个页面完成，避免功能分散
2. **操作清晰**：通过分类选择器明确区分不同类型的调整
3. **数据集中**：所有调整记录存储在同一张表中，便于统计和查询
4. **易于扩展**：未来如需新增其他类型的余额调整，只需扩展枚举值即可

## 技术要点

- 使用 Filament 的动态表单（`live()` 和 `visible()`）实现根据分类动态显示字段
- 使用 Eloquent 作用域（Scopes）简化查询逻辑
- 保持向后兼容性，避免影响现有功能
- 通过 URL 参数实现从仪表盘到列表页的筛选跳转

## 部署注意事项

1. 运行数据库迁移：`php artisan migrate`
2. 清除缓存：`php artisan cache:clear` 和 `composer dump-autoload`
3. 手动删除 `bootstrap/cache/filament` 目录（如果存在）
4. 测试各项功能是否正常工作

## 更新日期

2025-10-28

