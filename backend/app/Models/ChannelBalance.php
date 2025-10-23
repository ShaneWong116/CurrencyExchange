<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ChannelBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'date',
        'currency',
        'initial_amount',
        'income_amount',
        'outcome_amount',
        'current_balance'
    ];

    protected $casts = [
        'date' => 'date',
        'initial_amount' => 'decimal:2',
        'income_amount' => 'decimal:2',
        'outcome_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * 计算当前余额
     */
    public function calculateCurrentBalance()
    {
        $this->current_balance = $this->initial_amount + $this->income_amount - $this->outcome_amount;
        return $this->current_balance;
    }

    /**
     * 获取指定渠道和货币的今日余额
     */
    public static function getTodayBalance($channelId, $currency)
    {
        return static::where('channel_id', $channelId)
            ->where('currency', $currency)
            ->where('date', Carbon::today())
            ->first();
    }

    /**
     * 获取或创建今日余额记录
     */
    public static function getOrCreateTodayBalance($channelId, $currency, $initialAmount = 0)
    {
        return static::firstOrCreate([
            'channel_id' => $channelId,
            'currency' => $currency,
            'date' => Carbon::today(),
        ], [
            'initial_amount' => $initialAmount,
            'income_amount' => 0,
            'outcome_amount' => 0,
            'current_balance' => $initialAmount,
        ]);
    }
}
