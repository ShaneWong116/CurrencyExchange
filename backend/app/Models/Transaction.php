<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Transaction extends Model
{
    use HasFactory;
    
    /**
     * 临时存储更新前的旧数据（用于 updated 事件中计算余额变更）
     */
    protected static $pendingOldData = [];

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'transaction_label',
        'rmb_amount',
        'hkd_amount',
        'exchange_rate',
        'instant_rate',
        'instant_profit',
        'channel_id',
        'location_id',
        'location',
        'notes',
        'status',
        'settlement_status',
        'settlement_id',
        'settlement_date',
        'submit_time'
    ];

    protected $casts = [
        'rmb_amount' => 'decimal:2',
        'hkd_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:5',
        'instant_rate' => 'decimal:5',
        'instant_profit' => 'decimal:2',
        'submit_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
        });

        // 交易创建后更新统计
        static::created(function ($transaction) {
            // 更新仪表盘总统计
            $dashboardStats = CurrentStatistic::getOrCreate('dashboard');
            $dashboardStats->addTransaction($transaction);

            // 更新渠道统计
            $channelStats = CurrentStatistic::getOrCreate('channel', $transaction->channel_id);
            $channelStats->addTransaction($transaction);
            
            // 更新渠道余额（入账/出账交易）
            if (in_array($transaction->type, ['income', 'outcome'])) {
                static::updateChannelBalance($transaction);
            }
        });

        // 更新前检查：禁止编辑已结算的交易
        static::updating(function ($transaction) {
            if ($transaction->getOriginal('settlement_status') === 'settled') {
                throw new \Exception('不能编辑已结算的交易记录。如需修改，请先撤销相关的结算记录。');
            }
            
            // 暂存旧数据用于 updated 事件（使用静态属性确保数据不丢失）
            static::$pendingOldData[$transaction->id] = [
                'type' => $transaction->getOriginal('type'),
                'channel_id' => $transaction->getOriginal('channel_id'),
                'rmb_amount' => $transaction->getOriginal('rmb_amount'),
                'hkd_amount' => $transaction->getOriginal('hkd_amount'),
            ];
        });

        // 交易更新后处理余额变更（确保Filament后台修改也能同步余额）
        static::updated(function ($transaction) {
            $hasOldData = isset(static::$pendingOldData[$transaction->id]);
            
            \Log::info("Transaction {$transaction->id} updated event fired", [
                'isUnsettled' => $transaction->isUnsettled(),
                'current_settlement_status' => $transaction->settlement_status,
                'has_old_data' => $hasOldData,
            ]);
            
            // 仅处理未结算的 income/outcome
            if ($transaction->isUnsettled() && $hasOldData) {
                static::handleBalanceUpdate($transaction);
            } else {
                \Log::info("Transaction {$transaction->id} balance update skipped - conditions not met");
            }
            
            // 清理临时数据
            unset(static::$pendingOldData[$transaction->id]);
        });

        // 删除前检查：禁止删除已结算的交易
        static::deleting(function ($transaction) {
            if ($transaction->isSettled()) {
                throw new \Exception('不能删除已结算的交易记录。如需删除，请先撤销相关的结算记录。');
            }
        });

        // 交易删除后更新统计
        static::deleted(function ($transaction) {
            // 更新仪表盘总统计
            $dashboardStats = CurrentStatistic::where('stat_type', 'dashboard')->first();
            if ($dashboardStats) {
                $dashboardStats->removeTransaction($transaction);
            }

            // 更新渠道统计
            $channelStats = CurrentStatistic::where('stat_type', 'channel')
                ->where('reference_id', $transaction->channel_id)
                ->first();
            if ($channelStats) {
                $channelStats->removeTransaction($transaction);
            }
            
            // 回滚渠道余额（仅针对未结算的入账/出账交易）
            if (in_array($transaction->type, ['income', 'outcome'])) {
                static::revertChannelBalance($transaction);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(FieldUser::class, 'user_id');
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function isIncome()
    {
        return $this->type === 'income';
    }

    public function isOutcome()
    {
        return $this->type === 'outcome';
    }

    public function isExchange()
    {
        return $this->type === 'exchange';
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 关联到结余记录
     */
    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    /**
     * 是否已结余
     */
    public function isSettled()
    {
        return $this->settlement_status === 'settled';
    }

    /**
     * 是否未结余
     */
    public function isUnsettled()
    {
        return $this->settlement_status === 'unsettled';
    }

    /**
     * 查询未结余的交易
     */
    public function scopeUnsettled($query)
    {
        return $query->where('settlement_status', 'unsettled');
    }

    /**
     * 查询已结余的交易
     */
    public function scopeSettled($query)
    {
        return $query->where('settlement_status', 'settled');
    }
    
    /**
     * 更新渠道余额（入账/出账交易时实时更新）
     * 采用"当前余额 ± 本次交易"的累加模式
     */
    protected static function updateChannelBalance($transaction)
    {
        $today = Carbon::today();
        
        // 处理 RMB 余额
        static::updateCurrencyBalance(
            $transaction->channel_id, 
            'RMB', 
            $today, 
            $transaction->type, 
            $transaction->rmb_amount
        );
        
        // 处理 HKD 余额
        static::updateCurrencyBalance(
            $transaction->channel_id, 
            'HKD', 
            $today, 
            $transaction->type, 
            $transaction->hkd_amount
        );
    }
    
    /**
     * 更新指定货币的余额（真正的实时累加）
     * 使用数据库锁防止并发问题
     */
    protected static function updateCurrencyBalance($channelId, $currency, $today, $transactionType, $amount)
    {
        DB::transaction(function () use ($channelId, $currency, $today, $transactionType, $amount) {
            // 1. 获取或创建今日余额记录（使用行锁防止并发）
            $todayBalance = ChannelBalance::where('channel_id', $channelId)
                ->where('currency', $currency)
                ->where('date', $today)
                ->lockForUpdate() // 🔒 添加行锁
                ->first();
            
            if (!$todayBalance) {
                // 今天还没有记录，需要从历史继承
                $previousBalance = ChannelBalance::where('channel_id', $channelId)
                    ->where('currency', $currency)
                    ->where('date', '<', $today)
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate() // 🔒 读取历史余额时也加锁
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
            
            // 2. 根据交易类型和货币，计算余额变化
            // 入账：RMB+、HKD-；出账：RMB-、HKD+
            if ($transactionType === 'income') {
                if ($currency === 'RMB') {
                    // 入账时 RMB 增加
                    $todayBalance->income_amount += $amount;
                    $todayBalance->current_balance += $amount;
                } else {
                    // 入账时 HKD 减少
                    $todayBalance->income_amount += $amount;
                    $todayBalance->current_balance -= $amount;
                }
            } else { // outcome
                if ($currency === 'RMB') {
                    // 出账时 RMB 减少
                    $todayBalance->outcome_amount += $amount;
                    $todayBalance->current_balance -= $amount;
                } else {
                    // 出账时 HKD 增加
                    $todayBalance->outcome_amount += $amount;
                    $todayBalance->current_balance += $amount;
                }
            }
            
            // 3. 保存更新后的余额
            $todayBalance->save();
        });
    }
    
    /**
     * 处理交易更新导致的余额变更
     * 采用"先回滚旧值，再应用新值"的策略，可同时处理金额变更、类型变更和渠道变更
     */
    protected static function handleBalanceUpdate($transaction)
    {
        // 从静态属性获取旧数据
        $oldData = static::$pendingOldData[$transaction->id] ?? null;
        
        if (!$oldData) {
            \Log::warning("Transaction {$transaction->id} balance update skipped - no old data found");
            return;
        }
        
        $oldType = $oldData['type'];
        $oldChannelId = $oldData['channel_id'];
        $oldRmb = $oldData['rmb_amount'];
        $oldHkd = $oldData['hkd_amount'];

        \Log::info("Transaction {$transaction->id} balance update started", [
            'old_type' => $oldType,
            'old_channel_id' => $oldChannelId,
            'old_rmb' => $oldRmb,
            'old_hkd' => $oldHkd,
            'new_type' => $transaction->type,
            'new_channel_id' => $transaction->channel_id,
            'new_rmb' => $transaction->rmb_amount,
            'new_hkd' => $transaction->hkd_amount,
        ]);

        // 1. 回滚旧值 (针对最新余额)
        if (in_array($oldType, ['income', 'outcome'])) {
            $channel = Channel::find($oldChannelId);
            if ($channel) {
                // 入账回滚：RMB-, HKD+; 出账回滚：RMB+, HKD-
                $rmbDelta = ($oldType == 'income') ? -$oldRmb : $oldRmb;
                $hkdDelta = ($oldType == 'income') ? $oldHkd : -$oldHkd;
                
                \Log::info("Rolling back old values for channel {$oldChannelId}", [
                    'rmb_delta' => $rmbDelta,
                    'hkd_delta' => $hkdDelta,
                ]);
                
                $channel->adjustLatestBalance('RMB', $rmbDelta);
                $channel->adjustLatestBalance('HKD', $hkdDelta);
            }
        }

        // 2. 应用新值 (针对最新余额)
        if (in_array($transaction->type, ['income', 'outcome'])) {
             $channel = Channel::find($transaction->channel_id);
             if ($channel) {
                // 入账：RMB+, HKD-; 出账：RMB-, HKD+
                $rmbDelta = ($transaction->type == 'income') ? $transaction->rmb_amount : -$transaction->rmb_amount;
                $hkdDelta = ($transaction->type == 'income') ? -$transaction->hkd_amount : $transaction->hkd_amount;
                
                \Log::info("Applying new values for channel {$transaction->channel_id}", [
                    'rmb_delta' => $rmbDelta,
                    'hkd_delta' => $hkdDelta,
                ]);
                
                $channel->adjustLatestBalance('RMB', $rmbDelta);
                $channel->adjustLatestBalance('HKD', $hkdDelta);
             }
        }
        
        \Log::info("Transaction {$transaction->id} balance update completed");
    }

    /**
     * 回滚渠道余额（删除交易时调用）
     * 对updateChannelBalance的反向操作
     */
    protected static function revertChannelBalance($transaction)
    {
        // 获取交易创建日期（而不是今天）
        $transactionDate = Carbon::parse($transaction->created_at)->startOfDay();
        
        // 处理 RMB 余额回滚 (历史记录)
        static::revertCurrencyBalance(
            $transaction->channel_id, 
            'RMB', 
            $transactionDate, 
            $transaction->type, 
            $transaction->rmb_amount
        );
        
        // 处理 HKD 余额回滚 (历史记录)
        static::revertCurrencyBalance(
            $transaction->channel_id, 
            'HKD', 
            $transactionDate, 
            $transaction->type, 
            $transaction->hkd_amount
        );

        // 处理最新余额回滚 (如果交易不是今天的，历史记录回滚不会影响最新余额，所以需要额外处理)
        // 检查该渠道最新余额记录的日期
        $latestRmbDate = ChannelBalance::where('channel_id', $transaction->channel_id)
             ->where('currency', 'RMB')
             ->orderBy('date', 'desc')
             ->value('date');
             
        if ($latestRmbDate && $transactionDate->lt(Carbon::parse($latestRmbDate))) {
             $channel = $transaction->channel;
             if ($channel) {
                // 入账回滚：RMB-, HKD+
                $rmbDelta = ($transaction->type == 'income') ? -$transaction->rmb_amount : $transaction->rmb_amount;
                $hkdDelta = ($transaction->type == 'income') ? $transaction->hkd_amount : -$transaction->hkd_amount;
                
                $channel->adjustLatestBalance('RMB', $rmbDelta);
                $channel->adjustLatestBalance('HKD', $hkdDelta);
             }
        }
    }
    
    /**
     * 回滚指定货币的余额（删除交易时调用）
     */
    protected static function revertCurrencyBalance($channelId, $currency, $transactionDate, $transactionType, $amount)
    {
        // 查找交易日期的余额记录
        $balanceRecord = ChannelBalance::where('channel_id', $channelId)
            ->where('currency', $currency)
            ->where('date', $transactionDate)
            ->first();
        
        // 如果记录不存在，说明可能已经被清理或从未创建，直接返回
        if (!$balanceRecord) {
            return;
        }
        
        // 执行反向操作：减去之前加上的，加上之前减去的
        // 入账：RMB+、HKD-；出账：RMB-、HKD+
        if ($transactionType === 'income') {
            if ($currency === 'RMB') {
                // 回滚入账时的 RMB 增加
                $balanceRecord->income_amount -= $amount;
                $balanceRecord->current_balance -= $amount;
            } else {
                // 回滚入账时的 HKD 减少
                $balanceRecord->income_amount -= $amount;
                $balanceRecord->current_balance += $amount;
            }
        } else { // outcome
            if ($currency === 'RMB') {
                // 回滚出账时的 RMB 减少
                $balanceRecord->outcome_amount -= $amount;
                $balanceRecord->current_balance += $amount;
            } else {
                // 回滚出账时的 HKD 增加
                $balanceRecord->outcome_amount -= $amount;
                $balanceRecord->current_balance -= $amount;
            }
        }
        
        $balanceRecord->save();
    }
}
