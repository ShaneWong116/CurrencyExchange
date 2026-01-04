<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            // 更新仪表盘总统计（使用 try-catch 防止统计表不存在导致交易失败）
            try {
                $dashboardStats = CurrentStatistic::getOrCreate('dashboard');
                $dashboardStats->addTransaction($transaction);

                // 更新渠道统计
                $channelStats = CurrentStatistic::getOrCreate('channel', $transaction->channel_id);
                $channelStats->addTransaction($transaction);
            } catch (\Exception $e) {
                Log::warning('Failed to update current statistics', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // 注意：人民币余额不再在这里更新，因为 Channel::getRmbBalance() 已经基于未结算交易动态计算
            // 这样可以避免跨日录入交易时的重复计算问题
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
            
            Log::info("Transaction {$transaction->id} updated event fired", [
                'isUnsettled' => $transaction->isUnsettled(),
                'current_settlement_status' => $transaction->settlement_status,
                'has_old_data' => $hasOldData,
            ]);
            
            // 注意：人民币余额不再在这里更新，因为 Channel::getRmbBalance() 已经基于未结算交易动态计算
            // 这样可以避免跨日录入交易时的重复计算问题
            
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
            // 更新仪表盘总统计（使用 try-catch 防止统计表不存在导致删除失败）
            try {
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
            } catch (\Exception $e) {
                Log::warning('Failed to update current statistics on delete', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            // 注意：人民币余额不再在这里回滚，因为 Channel::getRmbBalance() 已经基于未结算交易动态计算
            // 删除交易后，该交易自然不会被计入余额
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
    
    // 注意：以下方法已被移除，因为人民币余额现在通过 Channel::getRmbBalance() 动态计算
    // - updateChannelBalance()
    // - updateCurrencyBalance()
    // - handleBalanceUpdate()
    // - revertChannelBalance()
    // - revertCurrencyBalance()
    // 
    // 这样可以避免跨日录入交易时的重复计算问题
    // 人民币余额 = ChannelBalance.initial_amount（上次结算后的基础余额）+ 未结算交易净额
}
