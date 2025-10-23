<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'previous_capital',
        'previous_hkd_balance',
        'profit',
        'other_expenses_total',
        'new_capital',
        'new_hkd_balance',
        'settlement_rate',
        'rmb_balance_total',
        'sequence_number',
        'notes',
    ];

    protected $casts = [
        'previous_capital' => 'decimal:2',
        'previous_hkd_balance' => 'decimal:2',
        'profit' => 'decimal:3',
        'other_expenses_total' => 'decimal:2',
        'new_capital' => 'decimal:2',
        'new_hkd_balance' => 'decimal:2',
        'settlement_rate' => 'decimal:5',
        'rmb_balance_total' => 'decimal:2',
    ];

    /**
     * 关联到已结余的交易
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * 关联到其他支出明细
     */
    public function expenses()
    {
        return $this->hasMany(SettlementExpense::class);
    }

    /**
     * 获取下一个结余序号
     */
    public static function getNextSequenceNumber()
    {
        $lastSettlement = static::orderBy('sequence_number', 'desc')->first();
        return $lastSettlement ? $lastSettlement->sequence_number + 1 : 1;
    }

    /**
     * 按时间范围查询
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
