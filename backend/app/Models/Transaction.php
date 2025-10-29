<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

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
}
