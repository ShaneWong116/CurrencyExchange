<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'label',
        'category',
        'status',
        'transaction_count'
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function drafts()
    {
        return $this->hasMany(TransactionDraft::class);
    }

    public function balances()
    {
        return $this->hasMany(ChannelBalance::class);
    }

    public function balanceAdjustments()
    {
        return $this->hasMany(BalanceAdjustment::class);
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function incrementTransactionCount()
    {
        $this->increment('transaction_count');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * 获取指定货币的当前余额（动态计算：昨日余额 + 今日交易净额 + 今日手动调整）
     */
    public function getCurrentBalance($currency)
    {
        $today = now()->toDateString();
        $yesterday = now()->copy()->subDay()->toDateString();

        // 昨日收盘余额（作为今日初始额）
        $yesterdayBalance = $this->balances()
            ->where('currency', $currency)
            ->where('date', $yesterday)
            ->first();
        $initialAmount = $yesterdayBalance ? (float) $yesterdayBalance->current_balance : 0.0;

        // 今日交易净额（按方向规则）：入账 RMB+、HKD-；出账 RMB-、HKD+
        if ($currency === 'RMB') {
            $netExpr = 'SUM(CASE WHEN type = "income" THEN rmb_amount WHEN type = "outcome" THEN -rmb_amount ELSE 0 END) as net';
        } else { // HKD
            $netExpr = 'SUM(CASE WHEN type = "income" THEN -hkd_amount WHEN type = "outcome" THEN hkd_amount ELSE 0 END) as net';
        }
        $todayNetFromTransactions = (float) $this->transactions()
            ->whereDate('created_at', $today)
            ->selectRaw($netExpr)
            ->value('net');

        // 今日手动调整净额（如有）
        if (method_exists($this, 'balanceAdjustments')) {
            $todayAdjustments = (float) $this->balanceAdjustments()
                ->where('currency', $currency)
                ->whereDate('created_at', $today)
                ->sum('adjustment_amount');
        } else {
            $todayAdjustments = 0.0;
        }

        return $initialAmount + $todayNetFromTransactions + $todayAdjustments;
    }

    /**
     * 获取RMB余额
     */
    public function getRmbBalance()
    {
        return $this->getCurrentBalance('RMB');
    }

    /**
     * 获取HKD余额
     */
    public function getHkdBalance()
    {
        return $this->getCurrentBalance('HKD');
    }
}
