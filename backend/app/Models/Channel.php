<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
     * 获取RMB余额（动态计算，按渠道区分）
     * 渠道当前余额 = 该渠道的人民币基础余额（initial_amount）+ 该渠道的未结算交易人民币净额
     * 
     * 计算公式：
     * baseBalance (该渠道的 initial_amount) + 未结算入账人民币 - 未结算出账人民币
     * 
     * 注意：
     * 1. 所有计算都按渠道区分：
     *    - baseBalance：从该渠道的 ChannelBalance 记录中读取
     *    - 未结算交易：只统计该渠道（channel_id）的交易记录
     * 
     * 2. 基础余额来源：ChannelBalance 表的 initial_amount（结算后的基础余额）
     */
    public function getRmbBalance()
    {
        // 获取该渠道的基础人民币结余（从该渠道的 ChannelBalance 表读取最新记录的 initial_amount）
        // 注意：$this->balances() 已经按渠道区分（hasMany 关系）
        $latestBalance = $this->balances()
            ->where('currency', 'RMB')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        $baseBalance = $latestBalance ? (float) $latestBalance->initial_amount : 0.0;
        
        // 计算该渠道所有未结算交易的人民币净额
        // 注意：$this->transactions() 已经按渠道区分（hasMany 关系，只返回该渠道的交易）
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
     * 获取HKD余额（动态计算，按渠道区分）
     * 渠道当前余额 = 该渠道的港币基础余额（initial_amount）+ 该渠道的未结算交易港币净额
     * 
     * 计算公式：
     * baseBalance (该渠道的 initial_amount) + 未结算出账港币 - 未结算入账港币
     * 
     * 注意：
     * 1. 所有计算都按渠道区分：
     *    - baseBalance：从该渠道的 ChannelBalance 记录中读取
     *    - 未结算交易：只统计该渠道（channel_id）的交易记录
     * 
     * 2. 港币方向与人民币相反：
     *    - 入账交易（income）：人民币增加，港币减少
     *    - 出账交易（outcome）：人民币减少，港币增加
     *    - 即时买断（instant_buyout）：不计入港币余额（它用于利润计算，不代表港币收付流水）
     * 
     * 3. 基础余额来源：ChannelBalance 表的 initial_amount（结算后的基础余额）
     */
    public function getHkdBalance()
    {
        // 获取该渠道的基础港币结余（从该渠道的 ChannelBalance 表读取最新记录的 initial_amount）
        // 注意：$this->balances() 已经按渠道区分（hasMany 关系）
        $latestBalance = $this->balances()
            ->where('currency', 'HKD')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        $baseBalance = $latestBalance ? (float) $latestBalance->initial_amount : 0.0;
        
        // 计算该渠道所有未结算交易的港币净额
        // 注意：$this->transactions() 已经按渠道区分（hasMany 关系，只返回该渠道的交易）
        // 出账增加港币，入账减少港币（港币方向与人民币相反）
        $unsettledOutcome = (float) $this->transactions()
            ->where('settlement_status', 'unsettled')
            ->where('type', 'outcome')
            ->sum('hkd_amount');
            
        $unsettledIncome = (float) $this->transactions()
            ->where('settlement_status', 'unsettled')
            ->where('type', 'income')
            ->sum('hkd_amount');

        return $baseBalance + $unsettledOutcome - $unsettledIncome;
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
            
            Log::debug("Channel {$this->id} {$currency} balance adjusted", [
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
            
            Log::debug("Channel {$this->id} {$currency} balance created", [
                'delta' => $delta,
                'new_balance' => $newBalance->current_balance,
            ]);
        }
    }
}
