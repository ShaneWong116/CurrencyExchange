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
     * 获取RMB余额（直接读取）
     */
    public function getRmbBalance()
    {
        return $this->getCurrentBalance('RMB');
    }

    /**
     * 获取HKD余额（直接读取）
     */
    public function getHkdBalance()
    {
        return $this->getCurrentBalance('HKD');
    }
}
