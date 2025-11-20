# 多店铺独立核算改造计划

## 📊 总览

- **数据库迁移**：3个文件（全部已创建✅）
  - 添加location_id字段
  - 移除渠道多余字段
  - 线上数据迁移脚本
- **Model层**：4个文件
- **Service层**：2个文件  
- **API Controller层**：3个文件
- **Filament Resource层**：5个文件
- **Filament Widget层**：6-8个文件
- **其他组件**：2个文件

**总计约27-32个文件需要修改**

---

## 📌 数据库配置说明

**当前配置**（`config/database.php`）：
- **默认数据库**：SQLite
- **支持的数据库**：MySQL、SQLite、PostgreSQL

**线上 Docker 环境**：
- **数据库类型**：SQLite
- **数据库位置**：`/var/www/html/database/database.sqlite`（容器内）
- **持久化方式**：Docker Volume `backend-database`
- **Docker 服务**：backend, nginx, queue, scheduler

**如何确认线上数据库类型**：
```bash
# Docker 环境
docker-compose exec backend php artisan tinker
>>> DB::getDriverName()
# 返回: 'sqlite'

# 或直接查看环境变量
docker-compose exec backend env | grep DB_CONNECTION
# 应该显示: DB_CONNECTION=sqlite
```

**迁移脚本已做兼容处理**：✅ 支持所有数据库类型

---

## 🚀 Docker 环境部署快速指南

### 极简版（5分钟）

```bash
# 1. SSH登录服务器并进入项目目录
ssh user@server && cd /path/to/ExchangeSystem

# 2. 备份数据库
docker cp currency-backend:/var/www/html/database/database.sqlite ./backup_$(date +%Y%m%d_%H%M%S).sqlite

# 3. 拉取最新代码
git pull origin main

# 4. 重启容器
docker-compose up -d --force-recreate backend queue scheduler

# 5. 执行迁移
docker-compose exec backend php artisan migrate --force

# 6. 验证
docker-compose exec backend php artisan tinker
>>> \App\Models\Location::where('code', 'DEFAULT')->first()
>>> \App\Models\Channel::whereNull('location_id')->count()  // 应该为 0
>>> exit

# 完成！
```

### 详细版（见下方完整步骤）

---

## 核心业务规则

1. **外勤人员**：绑定店铺，只能操作自己店铺数据，不能切换
2. **管理员**：可通过全局选择器查看/操作所有店铺
3. **渠道归属**：一个渠道只属于一个店铺
4. **渠道字段简化**：只保留名称、所属店铺、状态、累计交易次数（移除代码、标签、分类）
5. **三个余额**：本金、人民币余额、港币余额按店铺独立
6. **结算独立**：每个店铺单独结算
7. **删除渠道**：RMB余额必须=0
8. **人民币余额**：不可直接修改，只能改渠道余额（自动汇总）

---

## Phase 1: 数据库（已完成✅）

### 1.1 主迁移文件

**文件**: `database/migrations/2025_11_19_000001_add_location_id_to_multi_location_tables.php` ✅

**功能**:
- channels表添加location_id
- balance_adjustments表添加location_id  
- settlements表添加location_id
- **本地环境**：清空历史数据
- **线上环境**：不清空数据，字段添加成功

**数据库兼容性**：✅
- 支持 MySQL
- 支持 SQLite（项目默认）
- 支持 PostgreSQL

### 1.2 渠道表字段清理

**文件**: `database/migrations/2025_11_19_000002_simplify_channels_table.php` ✅

**功能**:
- 移除 `code` 列
- 移除 `label` 列
- 移除 `category` 列

### 1.3 线上数据迁移脚本 ⭐重要

**文件**: `database/migrations/2025_11_19_000003_migrate_existing_data_to_default_location.php` ✅

**功能**（仅在生产环境执行）:
1. 创建"默认店铺"（code='DEFAULT'）
2. 将所有现有渠道分配给默认店铺
3. 将所有现有余额调整分配给默认店铺
4. 将所有现有结算分配给默认店铺

**执行顺序**:
```bash
# 本地开发环境
php artisan migrate  # 会清空数据，新加字段

# 线上生产环境
php artisan migrate  # 不清空数据，先加字段，再迁移数据到默认店铺
```

---

## ⚠️ 线上部署注意事项

### 🐳 Docker 环境配置说明

**当前 Docker 服务**：
- `backend` - 后端 API 服务（主服务）
- `nginx` - Web 服务器
- `queue` - 队列处理服务
- `scheduler` - 定时任务服务

**数据库配置**：
- **类型**：SQLite
- **位置**：`/var/www/html/database/database.sqlite`（容器内）
- **持久化**：通过 Docker Volume `backend-database` 挂载

---

### 🔴 关键步骤

#### 1. 部署前准备（Docker 环境）

**Step 1: 检查 Docker 服务状态**
```bash
# 查看运行中的容器
docker-compose ps

# 应该看到以下容器运行中:
# - currency-backend
# - currency-nginx
# - currency-queue
# - currency-scheduler
```

**Step 2: 备份数据库（必须！）**
```bash
# 方式1：从容器内备份（推荐）
docker-compose exec backend cp /var/www/html/database/database.sqlite /var/www/html/database/database.sqlite.backup_$(date +%Y%m%d_%H%M%S)

# 方式2：从宿主机备份 Docker Volume
docker run --rm -v backend-database:/data -v $(pwd):/backup alpine \
  tar czf /backup/database_backup_$(date +%Y%m%d_%H%M%S).tar.gz -C /data .

# 方式3：直接复制数据库文件到宿主机
docker cp currency-backend:/var/www/html/database/database.sqlite ./backup_database_$(date +%Y%m%d_%H%M%S).sqlite
```

**Step 3: 验证当前环境**
```bash
# 检查数据库类型
docker-compose exec backend php artisan tinker
>>> DB::getDriverName()  // 应该返回 'sqlite'
>>> exit

# 检查数据库连接
docker-compose exec backend php artisan migrate:status
```

#### 2. 更新代码（Docker 环境）

**Step 1: 拉取最新代码到服务器**
```bash
# SSH 登录到服务器
ssh user@your-server

# 进入项目目录
cd /path/to/CurrencyExSystem/ExchangeSystem

# 拉取最新代码
git pull origin main
```

**Step 2: 重新构建 Docker 镜像**
```bash
# 如果使用本地构建
docker-compose build backend

# 如果使用远程镜像，先推送到镜像仓库
# 然后拉取最新镜像
docker-compose pull backend
```

**Step 3: 重启容器（使用新镜像）**
```bash
# 停止当前容器
docker-compose stop backend queue scheduler

# 启动新容器（会自动使用新镜像）
docker-compose up -d backend queue scheduler

# 或者一步到位
docker-compose up -d --force-recreate backend queue scheduler
```

#### 3. 执行数据库迁移（Docker 环境）

**⚠️ 重要：在低峰期执行，避免影响用户使用**

```bash
# Step 1: 查看待执行的迁移
docker-compose exec backend php artisan migrate:status

# 应该看到 3 个新的迁移待执行（状态为 Pending）
# - 2025_11_19_000001_add_location_id_to_multi_location_tables
# - 2025_11_19_000002_simplify_channels_table
# - 2025_11_19_000003_migrate_existing_data_to_default_location

# Step 2: 执行迁移
docker-compose exec backend php artisan migrate --force

# 迁移过程会输出：
# - 添加 location_id 字段
# - 移除 code/label/category 字段
# - 创建默认店铺
# - 迁移历史数据到默认店铺

# Step 3: 验证迁移成功
docker-compose exec backend php artisan migrate:status

# 所有迁移应该显示为 Ran（已执行）
```

#### 4. 验证数据完整性（Docker 环境）

**方式1：使用 Laravel Tinker（推荐，适用所有数据库）**
```bash
# 进入容器的 Tinker
docker-compose exec backend php artisan tinker

# 检查数据量（迁移前后应该一致）
>>> \App\Models\Channel::count()
>>> \App\Models\BalanceAdjustment::count()
>>> \App\Models\Settlement::count()

# 检查默认店铺是否创建成功
>>> $location = \App\Models\Location::where('code', 'DEFAULT')->first()
>>> $location->name  // 应该显示 "默认店铺"
>>> $location->id    // 记下这个ID

# 检查所有数据是否都已分配到店铺
>>> \App\Models\Channel::whereNull('location_id')->count()  // 应该为 0
>>> \App\Models\BalanceAdjustment::whereNull('location_id')->count()  // 应该为 0
>>> \App\Models\Settlement::whereNull('location_id')->count()  // 应该为 0

# 检查字段是否添加成功
>>> \App\Models\Channel::first()
>>> // 应该能看到 location_id 字段

>>> exit
```

**方式2：直接查询 SQLite 数据库**
```bash
# 进入容器
docker-compose exec backend bash

# 使用 SQLite 命令行
sqlite3 /var/www/html/database/database.sqlite

# 检查字段
.schema channels

# 检查数据量
SELECT COUNT(*) FROM channels;
SELECT COUNT(*) FROM balance_adjustments;
SELECT COUNT(*) FROM settlements;

# 检查默认店铺
SELECT * FROM locations WHERE code = 'DEFAULT';

# 检查数据是否都已分配
SELECT COUNT(*) FROM channels WHERE location_id IS NULL;  -- 应该为 0

# 退出 SQLite
.quit

# 退出容器
exit
```

**方式3：从宿主机查看日志**
```bash
# 查看迁移日志
docker-compose logs backend | grep -i "migrate\|location"

# 应该看到类似输出：
# ✅ 数据迁移完成：
#    - 渠道: X 条
#    - 余额调整: X 条
#    - 结算: X 条
#    - 默认店铺ID: X
```

#### 5. 回滚方案（Docker 环境，如果出现问题）

**⚠️ 只在出现严重问题时使用！**

**方案1：使用 Laravel 回滚（推荐，可恢复）**
```bash
# 回滚最近的 3 个迁移
docker-compose exec backend php artisan migrate:rollback --step=3

# 确认回滚成功
docker-compose exec backend php artisan migrate:status

# 如果需要，可以重新执行迁移
docker-compose exec backend php artisan migrate --force
```

**方案2：恢复数据库备份（彻底恢复）**
```bash
# 停止所有服务（避免数据冲突）
docker-compose stop

# 方式1：从容器内备份恢复
docker-compose start backend
docker-compose exec backend cp /var/www/html/database/database.sqlite.backup_YYYYMMDD_HHMMSS /var/www/html/database/database.sqlite

# 方式2：从宿主机备份恢复
docker cp ./backup_database_YYYYMMDD_HHMMSS.sqlite currency-backend:/var/www/html/database/database.sqlite

# 方式3：恢复整个 Docker Volume
docker run --rm -v backend-database:/data -v $(pwd):/backup alpine \
  tar xzf /backup/database_backup_YYYYMMDD_HHMMSS.tar.gz -C /data

# 重启所有服务
docker-compose up -d

# 验证恢复成功
docker-compose exec backend php artisan migrate:status
```

**方案3：重新构建容器（极端情况）**
```bash
# 完全停止并删除容器
docker-compose down

# 恢复数据库备份到宿主机
# (根据具体情况操作)

# 重新启动服务
docker-compose up -d

# 验证服务正常
docker-compose ps
docker-compose logs -f backend
```

### 📝 Docker 部署检查清单

**部署前**
- [ ] Docker 服务运行正常（`docker-compose ps`）
- [ ] **确认数据库类型**：SQLite
- [ ] 数据库已备份（至少 3 个备份）
- [ ] 确认当前环境（production）
- [ ] 代码已推送到 Git 仓库
- [ ] 在低峰期执行（建议凌晨或用户少时）

**部署中**
- [ ] 最新代码已拉取到服务器
- [ ] Docker 镜像已更新
- [ ] 容器已重启（backend, queue, scheduler）
- [ ] 迁移命令执行成功（`docker-compose exec backend php artisan migrate --force`）
- [ ] 所有 3 个迁移都显示为 Ran

**部署后验证**
- [ ] 数据完整性验证通过（Tinker 检查）
- [ ] 默认店铺创建成功
- [ ] 所有历史数据已分配到默认店铺
- [ ] 前端管理后台能正常访问
- [ ] 外勤 APP 能正常登录
- [ ] 创建交易功能正常
- [ ] 结算功能正常
- [ ] 日志无严重错误（`docker-compose logs backend`）

**回滚准备**
- [ ] 回滚命令已准备好
- [ ] 备份文件路径已确认
- [ ] 知道如何快速恢复服务

---

## Phase 2: Model层（4个文件）

### Channel.php

**字段调整**：
- 移除：`code`、`label`、`category`
- 保留：`name`、`status`、`transaction_count`
- 新增：`location_id`

**修改内容**：
```php
// 1. fillable调整
protected $fillable = [
    'location_id',  // 新增
    'name',
    'status',
    'transaction_count'
    // 移除：'code', 'label', 'category'
];

// 2. 添加location()关联
public function location()
{
    return $this->belongsTo(Location::class);
}

// 3. 添加scopeByLocation($query, $locationId)
public function scopeByLocation($query, $locationId)
{
    if ($locationId) {
        return $query->where('location_id', $locationId);
    }
    return $query;
}

// 4. 添加canDelete()检查RMB余额
public function canDelete(): bool
{
    return $this->getRmbBalance() == 0;
}

// 5. boot()中添加删除验证
protected static function boot()
{
    parent::boot();
    
    static::deleting(function ($channel) {
        if ($channel->getRmbBalance() != 0) {
            throw new \Exception('无法删除渠道：RMB余额必须为0');
        }
    });
}
```

### BalanceAdjustment.php
```php
// 1. fillable添加'location_id'
// 2. 添加location()关联
// 3. getCurrentCapital(?int $locationId = null)
// 4. getCurrentHkdBalance(?int $locationId = null)
// 5. createCapitalAdjustment(int $locationId, ...)
// 6. createHkdBalanceAdjustment(int $locationId, ...)
```

### Settlement.php
```php
// 1. fillable添加'location_id'
// 2. 添加location()关联
// 3. hasSettledToday(?int $locationId = null)
// 4. getTodaySettlement(?int $locationId = null)
```

### Location.php
```php
// 1. 添加channels()、settlements()、balanceAdjustments()关联
// 2. 添加getCapital()、getRmbBalance()、getHkdBalance()统计方法
```

---

## Phase 3: Service层（2个文件）

### SettlementService.php

**所有方法添加$locationId参数**：

```php
checkTodaySettlement(int $locationId)
getPreview(int $locationId, $instantBuyoutRate = null)
executeSettlement($data, int $locationId)
getUsedSettlementDates(int $locationId, $days = 60)
getRecommendedSettlementDate(int $locationId)
rollbackSettlement($settlementId) // 添加店铺验证
```

**核心修改点**：
- Channel查询添加：`where('location_id', $locationId)`
- Transaction查询添加：`whereHas('channel', fn($q) => $q->where('location_id', $locationId))`
- BalanceAdjustment调用传入$locationId

### ReportService.php

**所有报表方法添加?int $locationId = null参数**：
- null = 汇总所有店铺
- 有值 = 只查该店铺

```php
getDailyReport($date, ?int $locationId = null)
getMonthlyReport($month, ?int $locationId = null)
// ... 其他报表方法
```

---

## Phase 4: API Controller层（3个文件）

### 所有Controller添加辅助方法

```php
protected function getFieldUserLocationId(): int
{
    $user = auth('field')->user();
    if (!$user || !$user->location_id) {
        throw new \Exception('外勤人员未绑定店铺');
    }
    return $user->location_id;
}
```

### TransactionController.php
- index(): 按店铺筛选交易列表
- store(): 验证渠道归属

### DraftController.php
- 同TransactionController

### SettlementController.php
- preview(): 传入locationId
- store(): 传入locationId  
- checkToday(): 传入locationId
- rollback(): 验证店铺权限

---

## Phase 5: 全局组件（2个文件）

### LocationHelper.php（新建）

```php
// app/Helpers/LocationHelper.php
class LocationHelper {
    public static function getSelectedLocationId(): ?int
    public static function setSelectedLocationId(?int $locationId): void
}

// 全局函数
function getSelectedLocationId(): ?int
function setSelectedLocationId(?int $locationId): void
```

### Filament Panel配置
- 在顶部添加店铺选择下拉框（Livewire组件）
- 选项：各店铺 + "全部店铺"
- 保存到session

---

## Phase 6: Filament Resource层（5个文件）

### ChannelResource.php

#### 字段简化说明
**移除字段**：
- `code`（渠道代码）
- `label`（标签）
- `category`（分类）

**保留字段**：
- `name`（渠道名称）
- `location_id`（所属店铺）⭐新增
- `status`（状态）
- `transaction_count`（累计交易次数）

#### Form 表单
```php
Forms\Components\Section::make('渠道信息')
    ->schema([
        // 店铺选择（新增，创建后不可修改）
        Forms\Components\Select::make('location_id')
            ->label('所属店铺')
            ->relationship('location', 'name')
            ->required()
            ->disabled(fn (string $context) => $context === 'edit')
            ->helperText('创建后不可修改'),
            
        // 渠道名称
        Forms\Components\TextInput::make('name')
            ->label('渠道名称')
            ->required()
            ->maxLength(100)
            ->placeholder('例如：微信支付、支付宝等'),
            
        // 状态
        Forms\Components\Select::make('status')
            ->label('状态')
            ->options([
                'active' => '启用',
                'inactive' => '停用',
            ])
            ->required()
            ->default('active'),
            
        // 累计交易次数（只在编辑时显示）
        Forms\Components\TextInput::make('transaction_count')
            ->label('累计交易次数')
            ->suffix('笔')
            ->numeric()
            ->disabled()
            ->visible(fn (string $context) => $context === 'edit'),
    ])
    ->columns(2),
```

#### Table 列表
```php
->columns([
    TextColumn::make('id')
        ->label('ID')
        ->sortable(),
    
    // 店铺列（新增）
    TextColumn::make('location.name')
        ->label('所属店铺')
        ->searchable()
        ->sortable()
        ->badge()
        ->color('primary'),
        
    TextColumn::make('name')
        ->label('渠道名称')
        ->searchable()
        ->sortable()
        ->weight('bold'),
        
    TextColumn::make('status')
        ->label('状态')
        ->badge()
        ->color(fn (string $state): string => match ($state) {
            'active' => 'success',
            'inactive' => 'danger',
            default => 'secondary',
        })
        ->formatStateUsing(fn (string $state): string => 
            $state === 'active' ? '启用' : '停用'),
        
    // 累计交易次数（可点击跳转）
    TextColumn::make('transaction_count')
        ->label('累计交易')
        ->suffix(' 笔')
        ->sortable()
        ->url(function (Channel $record) {
            // 点击跳转到该渠道的交易记录列表
            return TransactionResource::getUrl('index', [
                'tableFilters' => [
                    'channel_id' => ['value' => $record->id],
                ],
            ]);
        })
        ->color('info')
        ->icon('heroicon-o-arrow-right-circle'),
        
    TextColumn::make('rmb_balance')
        ->label('人民币余额')
        ->prefix('¥')
        ->numeric(2)
        ->state(fn (Channel $record): float => $record->getRmbBalance())
        ->color(fn (float $state) => $state > 0 ? 'success' : 'danger'),
        
    TextColumn::make('hkd_balance')
        ->label('港币余额')
        ->prefix('HK$')
        ->numeric(2)
        ->state(fn (Channel $record): float => $record->getHkdBalance())
        ->color(fn (float $state) => $state > 0 ? 'success' : 'danger'),
        
    TextColumn::make('created_at')
        ->label('创建时间')
        ->dateTime('Y-m-d H:i')
        ->sortable()
        ->toggleable(isToggledHiddenByDefault: true),
])

// 添加查询修改器（按全局选择器筛选店铺）
->modifyQueryUsing(function (Builder $query) {
    $locationId = getSelectedLocationId();
    if ($locationId) {
        $query->where('location_id', $locationId);
    }
})

// 筛选器
->filters([
    SelectFilter::make('location_id')
        ->label('店铺')
        ->relationship('location', 'name')
        ->preload(),
        
    SelectFilter::make('status')
        ->label('状态')
        ->options([
            'active' => '启用',
            'inactive' => '停用',
        ]),
])

// 删除操作（添加RMB余额检查）
->actions([
    Tables\Actions\ViewAction::make(),
    
    Tables\Actions\EditAction::make()
        ->visible(fn() => auth()->user()->canManageChannels()),
        
    Tables\Actions\DeleteAction::make()
        ->visible(fn() => auth()->user()->canManageChannels())
        ->before(function (Channel $record, Tables\Actions\DeleteAction $action) {
            $rmbBalance = $record->getRmbBalance();
            if ($rmbBalance != 0) {
                Notification::make()
                    ->danger()
                    ->title('无法删除渠道')
                    ->body('渠道RMB余额必须为0才能删除。当前余额：¥' . number_format($rmbBalance, 2))
                    ->persistent()
                    ->send();
                
                $action->cancel();
            }
        }),
])
```

### BalanceAdjustmentResource.php
**Form**:
- 添加location_id选择
- 根据location_id动态获取本金/港币余额
- 渠道选择器按location_id筛选

**Table**:
- 添加location列和筛选器
- 查询按全局选择器筛选

**关键**:
- 首页人民币余额卡片链接改为跳转到渠道列表页（不再跳余额调整）

### SettlementResource.php
**Form**:
- 显示当前店铺信息
- 创建时关联location_id

**Table**:
- 添加location列和筛选器
- 查询按全局选择器筛选

### TransactionResource.php
**Form**:
- 渠道选择器按location筛选

**Table**:
- 显示店铺列（通过channel关联）
- 查询按全局选择器筛选

### FieldUserResource.php
**Table**:
- 添加location筛选器
- 按店铺分组显示

---

## Phase 7: Widget层（6-8个文件）

### BalanceOverview.php（核心）

```php
protected function getStats(): array
{
    $locationId = getSelectedLocationId();
    
    // 本金
    $capital = BalanceAdjustment::getCurrentCapital($locationId);
    
    // 港币余额
    $hkdBalance = BalanceAdjustment::getCurrentHkdBalance($locationId);
    
    // 人民币余额（按店铺筛选渠道）
    $rmbBalance = Channel::when($locationId, fn($q) => $q->where('location_id', $locationId))
        ->where('status', 'active')
        ->get()
        ->sum(fn($channel) => $channel->getRmbBalance());
    
    // 人民币余额卡片链接改为渠道列表页
    Stat::make('人民币余额', '¥' . number_format($rmbBalance, 2))
        ->url(ChannelResource::getUrl('index', ['location' => $locationId]))
}
```

### 其他Widget
- StatsOverview
- ChannelOverview
- SettlementStatsWidget
- TransactionSummary
- PrimaryNetInflow
- InstantBuyoutTable

**统一修改**：所有统计查询都按`getSelectedLocationId()`筛选

---

## 实施步骤

### Day 1: 基础（数据库+Model）
1. ✅ 运行迁移（清空数据）
2. 修改4个Model文件
3. 测试Model层方法

### Day 2: 业务逻辑（Service+API）
1. 修改SettlementService
2. 修改ReportService
3. 修改3个API Controller
4. 测试API接口

### Day 3: 后台基础（Resource）
1. 创建全局店铺选择器
2. 修改ChannelResource
3. 修改BalanceAdjustmentResource
4. 测试创建/编辑/删除

### Day 4: 后台完善（Widget+其他）
1. 修改BalanceOverview（核心）
2. 修改其他Widget
3. 修改SettlementResource、TransactionResource
4. 修改首页卡片链接
5. 全面测试

---

## 关键注意事项

### 1. 外勤权限控制
- 所有API接口必须验证location_id
- 不允许跨店铺操作

### 2. 管理员全局查看
- getSelectedLocationId() = null时，查询所有店铺
- 前端显示"全部店铺"选项

### 3. 人民币余额
- 不可直接修改
- 首页卡片链接到渠道列表，而非余额调整页

### 4. 删除渠道
- 必须检查RMB余额=0
- 在Model的boot()和Resource的DeleteAction都要验证

### 5. 结算隔离
- 每个店铺独立结算
- 结算时只处理该店铺的交易

---

## 测试清单

### 功能测试
- [ ] 创建渠道时必须选择店铺
- [ ] 创建渠道时只有名称、店铺、状态字段（无代码、标签、分类）
- [ ] 编辑渠道时不能修改店铺
- [ ] 删除渠道时验证RMB余额=0
- [ ] 点击渠道的累计交易次数能跳转到该渠道的交易记录
- [ ] 管理员切换店铺后数据正确显示
- [ ] 外勤人员只能看到自己店铺数据
- [ ] 结算只处理当前店铺交易
- [ ] 人民币余额=所有渠道RMB余额之和
- [ ] 报表按店铺正确筛选

### 权限测试
- [ ] 外勤人员无法使用其他店铺渠道
- [ ] 外勤人员无法结算其他店铺
- [ ] 管理员可以查看所有店铺

### 数据一致性测试
- [ ] 本金调整只影响该店铺
- [ ] 港币余额调整只影响该店铺
- [ ] 结算后余额更新正确
- [ ] 跨店铺数据不互相影响

---

## 完成标志

✅ 所有测试用例通过  
✅ 外勤APP能正常结算单个店铺  
✅ 后台能切换店铺查看数据  
✅ 首页三个余额卡片数据正确  
✅ 渠道删除验证生效  
✅ 人民币余额卡片链接到渠道列表

---

**预计工期：4个工作日**

---

## ❓ Docker 环境常见问题

### Q1: 如何查看 Docker 容器日志？
```bash
# 查看 backend 容器日志
docker-compose logs -f backend

# 查看最近 100 行日志
docker-compose logs --tail=100 backend

# 查看所有服务日志
docker-compose logs -f
```

### Q2: 迁移卡住了怎么办？
```bash
# 检查容器是否运行
docker-compose ps

# 进入容器查看
docker-compose exec backend bash
ps aux | grep php

# 如果确实卡住，强制重启
docker-compose restart backend
```

### Q3: 如何进入容器调试？
```bash
# 进入 backend 容器
docker-compose exec backend bash

# 或直接执行命令
docker-compose exec backend php artisan --version
docker-compose exec backend php artisan route:list
```

### Q4: 数据库文件在宿主机哪里？
```bash
# 查看 Docker Volume 位置
docker volume inspect backend-database

# 找到 Mountpoint，例如:
# /var/lib/docker/volumes/exchangesystem_backend-database/_data

# 注意：不要直接修改这个目录的文件，通过容器操作
```

### Q5: 如何清理 Docker 资源？
```bash
# 清理未使用的容器
docker-compose down --remove-orphans

# 清理未使用的镜像
docker image prune -a

# 清理所有（谨慎使用，会删除所有未使用的资源）
docker system prune -a
```

### Q6: 迁移后前端报错怎么办？
```bash
# 清除缓存
docker-compose exec backend php artisan cache:clear
docker-compose exec backend php artisan config:clear
docker-compose exec backend php artisan route:clear
docker-compose exec backend php artisan view:clear

# 重新优化
docker-compose exec backend php artisan config:cache
docker-compose exec backend php artisan route:cache

# 重启所有容器
docker-compose restart
```

### Q7: 如何临时停止服务进行维护？
```bash
# 停止所有服务
docker-compose stop

# 只停止 backend（其他继续运行）
docker-compose stop backend

# 恢复服务
docker-compose start
# 或
docker-compose up -d
```

### Q8: 容器重启后数据还在吗？
**答**：在！Docker Volume 持久化了以下数据：
- `backend-database`: 数据库文件
- `backend-storage`: 上传文件和日志
- `backend-public`: 公共资源文件

只要没有执行 `docker-compose down -v`（删除 Volume），数据就不会丢失。

---

## 📞 紧急联系

如果部署过程中遇到无法解决的问题：

1. **立即回滚**：使用上述回滚方案恢复服务
2. **保存日志**：`docker-compose logs > error_log.txt`
3. **联系技术支持**：提供错误日志和操作步骤

---

**预计工期：4个工作日**
