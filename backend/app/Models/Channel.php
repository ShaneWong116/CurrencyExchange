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
     * 获取指定货币的当前余额（从余额表读取最新记录）
     * 余额是累积值，直接读取最新的 current_balance
     */
    public function getCurrentBalance($currency)
    {
        // 获取该渠道该货币的最新余额记录
        $balance = $this->balances()
            ->where('currency', $currency)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        return $balance ? (float) $balance->current_balance : 0.0;
    }

    /**
     * 获取RMB余额（动态计算）
     * 渠道当前余额 = 渠道人民币结余（基础余额）+ 未结算入账人民币 - 未结算出账人民币
     * 
     * 基础余额来源：ChannelBalance 表的 initial_amount（结算后的基础余额）
     */
    public function getRmbBalance()
    {
        // 获取渠道的基础人民币结余（从 ChannelBalance 表读取最新记录的 initial_amount）
        $latestBalance = $this->balances()
            ->where('currency', 'RMB')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        $baseBalance = $latestBalance ? (float) $latestBalance->initial_amount : 0.0;
        
        // 计算该渠道所有未结算交易的人民币净额
        // 入账增加人民币，出账减少人民币
        $unsettledIncome = (float) $this->transactions()
            ->where('settlement_status', 'unsettled')
            ->where('type', 'income')
            ->sum('rmb_amount');
            
        $unsettledOutcome = (float) $this->transactions()
            ->where('settlement_status', 'unsettled')
            ->where('type', 'outcome')
            ->sum('rmb_amount');
        
        return $baseBalance + $unsettledIncome - $unsettledOutcome;
    }

    /**
     * 获取HKD余额（直接读取）
     */
    public function getHkdBalance()
    {
        return $this->getCurrentBalance('HKD');
    }

    /**
     * 调整指定货币的最新余额
     * 
     * @param string $currency 货币类型 (RMB/HKD)
     * @param float $delta 变动金额（正增负减）
     */
    public function adjustLatestBalance($currency, $delta)
    {
        if ($delta == 0) return;

        $balance = $this->balances()
            ->where('currency', $currency)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($balance) {
            $balance->current_balance += $delta;
            $balance->save();
            
            \Log::debug("Channel {$this->id} {$currency} balance adjusted", [
                'delta' => $delta,
                'new_balance' => $balance->current_balance,
            ]);
        } else {
            // 如果没有余额记录，创建一个
            $today = \Carbon\Carbon::today();
            $newBalance = ChannelBalance::create([
                'channel_id' => $this->id,
                'currency' => $currency,
                'date' => $today,
                'initial_amount' => 0,
                'income_amount' => 0,
                'outcome_amount' => 0,
                'current_balance' => $delta,
            ]);
            
            \Log::debug("Channel {$this->id} {$currency} balance created", [
                'delta' => $delta,
                'new_balance' => $newBalance->current_balance,
            ]);
        }
    }
}
