<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettlementExpense extends Model
{
    use HasFactory;

    // 类型常量
    const TYPE_EXPENSE = 'expense';  // 支出
    const TYPE_INCOME = 'income';    // 收入

    protected $fillable = [
        'settlement_id',
        'type',
        'item_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * 关联到结余记录
     */
    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    /**
     * 是否为支出
     */
    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    /**
     * 是否为收入
     */
    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    /**
     * 获取类型标签
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_EXPENSE => '支出',
            self::TYPE_INCOME => '收入',
            default => '未知',
        };
    }

    /**
     * 查询支出项
     */
    public function scopeExpenses($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * 查询收入项
     */
    public function scopeIncomes($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }
}
