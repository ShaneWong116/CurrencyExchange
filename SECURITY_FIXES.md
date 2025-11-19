# 安全修复指南

## 立即需要修复的问题

### 1. 注册管理员权限中间件

**文件**: `app/Http/Kernel.php`

在 `$routeMiddleware` 数组中添加：

```php
protected $routeMiddleware = [
    // ... 其他中间件
    'admin' => \App\Http\Middleware\CheckAdminRole::class,
];
```

### 2. 修改 API 路由，添加权限检查

**文件**: `routes/api.php`

```php
// 后台管理路由 - 添加 admin 中间件
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // 余额管理
    Route::get('/balance/overview', [\App\Http\Controllers\Api\Admin\BalanceController::class, 'overview']);
    // ... 其他管理路由
});
```

### 3. 添加速率限制

**文件**: `routes/api.php`

```php
// 批量操作添加限流
Route::post('/transactions/batch', [\App\Http\Controllers\Api\TransactionController::class, 'batchStore'])
    ->middleware('throttle:10,1'); // 每分钟10次

Route::post('/images/batch', [\App\Http\Controllers\Api\ImageController::class, 'batchUpload'])
    ->middleware('throttle:5,1'); // 每分钟5次

// 数据清理严格限流
Route::post('/admin/data/cleanup', [\App\Http\Controllers\Api\CleanupController::class, 'cleanup'])
    ->middleware('throttle:1,10'); // 每10分钟1次
```

### 4. 修复密码验证（防止时序攻击）

**文件**: `app/Http/Controllers/Api/CleanupController.php`

```php
public function cleanup(Request $request)
{
    // ... 验证逻辑
    
    $expected = env('ADMIN_VERIFY_PASSWORD');
    // 使用 hash_equals 防止时序攻击
    if (!$expected || !hash_equals($expected, $request->verification_password)) {
        return response()->json([
            'success' => false,
            'error_code' => 1003,
            'message' => '二次验证密码错误',
        ], 403);
    }
    
    // ... 其余代码
}
```

**同样修改**: `app/Http/Controllers/Api/SettlementController.php` 中的密码验证

### 5. 添加并发控制（数据库锁）

**文件**: `app/Models/Transaction.php`

修改 `updateCurrencyBalance` 方法：

```php
protected static function updateCurrencyBalance($channelId, $currency, $today, $transactionType, $amount)
{
    DB::transaction(function () use ($channelId, $currency, $today, $transactionType, $amount) {
        // 使用行锁防止并发问题
        $todayBalance = ChannelBalance::where('channel_id', $channelId)
            ->where('currency', $currency)
            ->where('date', $today)
            ->lockForUpdate() // 🔒 添加行锁
            ->first();
        
        if (!$todayBalance) {
            // 获取历史余额时也加锁
            $previousBalance = ChannelBalance::where('channel_id', $channelId)
                ->where('currency', $currency)
                ->where('date', '<', $today)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate() // 🔒 添加行锁
                ->first();
            
            $initialAmount = $previousBalance ? $previousBalance->current_balance : 0;
            
            // 创建今日记录
            $todayBalance = ChannelBalance::create([
                'channel_id' => $channelId,
                'currency' => $currency,
                'date' => $today,
                'initial_amount' => $initialAmount,
                'income_amount' => 0,
                'outcome_amount' => 0,
                'current_balance' => $initialAmount,
            ]);
        }
        
        // 根据交易类型更新余额
        if ($transactionType === 'income') {
            if ($currency === 'RMB') {
                $todayBalance->income_amount += $amount;
                $todayBalance->current_balance += $amount;
            } else {
                $todayBalance->income_amount += $amount;
                $todayBalance->current_balance -= $amount;
            }
        } else {
            if ($currency === 'RMB') {
                $todayBalance->outcome_amount += $amount;
                $todayBalance->current_balance -= $amount;
            } else {
                $todayBalance->outcome_amount += $amount;
                $todayBalance->current_balance += $amount;
            }
        }
        
        $todayBalance->save();
    });
}
```

### 6. 优化错误处理（不泄露敏感信息）

**文件**: `app/Http/Controllers/Api/TransactionController.php`

```php
} catch (\Exception $e) {
    DB::rollBack();
    
    // 记录详细错误到日志
    \Log::error('Transaction creation failed', [
        'user_id' => $request->user()->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // 返回通用错误信息（生产环境）
    $message = app()->environment('production') 
        ? '操作失败，请稍后重试' 
        : $e->getMessage();
    
    return response()->json([
        'message' => $message,
        'error_code' => 'TRANSACTION_CREATE_FAILED'
    ], 500);
}
```

### 7. 增强图片上传验证

**文件**: `app/Http/Controllers/Api/ImageController.php`

```php
public function store(Request $request)
{
    $request->validate([
        'image' => 'required|image|mimes:jpeg,jpg,png|max:5120', // 明确指定MIME类型
        'transaction_id' => 'nullable|exists:transactions,id',
        'draft_id' => 'nullable|exists:transaction_drafts,id',
    ]);
    
    // ... 权限验证
    
    $file = $request->file('image');
    
    try {
        // 验证是否真的是图片
        $image = ImageProcessor::make($file);
        
        // 检查图片有效性
        if (!$image->width() || !$image->height()) {
            return response()->json([
                'message' => '无效的图片文件',
                'error_code' => 'INVALID_IMAGE'
            ], 400);
        }
        
        // 检查图片尺寸限制
        if ($image->width() > 10000 || $image->height() > 10000) {
            return response()->json([
                'message' => '图片尺寸过大',
                'error_code' => 'IMAGE_TOO_LARGE'
            ], 400);
        }
        
        // ... 其余处理代码
        
    } catch (\Intervention\Image\Exception\NotReadableException $e) {
        return response()->json([
            'message' => '无效的图片文件',
            'error_code' => 'INVALID_IMAGE'
        ], 400);
    } catch (\Exception $e) {
        \Log::error('Image upload failed', [
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'message' => '图片上传失败',
            'error_code' => 'IMAGE_UPLOAD_FAILED'
        ], 500);
    }
}
```

### 8. 批量上传添加总大小限制

**文件**: `app/Http/Controllers/Api/ImageController.php`

```php
public function batchUpload(Request $request)
{
    $request->validate([
        'images' => 'required|array|max:10|min:1',
        'images.*' => 'required|string', // Base64
        'transaction_id' => 'nullable|exists:transactions,id',
        'draft_id' => 'nullable|exists:transaction_drafts,id',
    ]);
    
    // 计算总大小（Base64解码后）
    $totalSize = 0;
    foreach ($request->images as $base64Image) {
        $totalSize += strlen(base64_decode($base64Image));
    }
    
    // 限制总大小为 50MB
    if ($totalSize > 50 * 1024 * 1024) {
        return response()->json([
            'message' => '批量上传总大小超过限制(50MB)',
            'error_code' => 'BATCH_SIZE_EXCEEDED'
        ], 400);
    }
    
    // ... 其余代码
}
```

### 9. 添加操作审计日志

**创建新文件**: `app/Traits/Auditable.php`

```php
<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * 记录操作日志
     */
    protected function audit(string $action, $model = null, array $details = [])
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'details' => $details,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
```

**在控制器中使用**:

```php
use App\Traits\Auditable;

class SettlementController extends Controller
{
    use Auditable;
    
    public function store(Request $request)
    {
        // ... 结算逻辑
        
        $settlement = Settlement::create([...]);
        
        // 记录审计日志
        $this->audit('settlement.created', $settlement, [
            'settlement_date' => $request->settlement_date,
            'new_capital' => $request->new_capital,
        ]);
        
        // ...
    }
}
```

---

## 环境配置

### .env 文件中添加

```env
# 速率限制配置
THROTTLE_BATCH_REQUESTS=10  # 批量操作每分钟次数
THROTTLE_ADMIN_REQUESTS=60  # 管理操作每分钟次数

# 文件上传限制
MAX_IMAGE_SIZE=5120  # KB
MAX_BATCH_IMAGE_SIZE=51200  # KB (50MB)

# 审计日志
AUDIT_LOG_ENABLED=true
AUDIT_LOG_RETENTION_DAYS=90  # 审计日志保留天数
```

---

## 数据库迁移

### 创建审计日志表（如果还没有）

```bash
php artisan make:migration create_audit_logs_table
```

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('action');
    $table->string('model_type')->nullable();
    $table->unsignedBigInteger('model_id')->nullable();
    $table->json('details')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
    
    $table->index(['user_id', 'created_at']);
    $table->index(['model_type', 'model_id']);
    $table->index('action');
});
```

---

## 测试清单

修复后请测试以下场景：

- [ ] 非管理员访问 `/api/admin/*` 路由应返回 403
- [ ] 批量操作超过限流应返回 429
- [ ] 错误响应不包含敏感信息（生产环境）
- [ ] 并发创建交易，余额计算正确
- [ ] 上传非图片文件应被拒绝
- [ ] 审计日志正确记录关键操作
- [ ] 密码验证使用 hash_equals

---

**优先级**: 高  
**预计修复时间**: 2-4 小时  
**建议在**: 下一个维护窗口期完成修复
