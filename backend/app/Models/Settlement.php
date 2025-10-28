<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_date',
        'previous_capital',
        'previous_hkd_balance',
        'profit',
        'outgoing_profit',
        'instant_profit',
        'instant_buyout_rate',
        'other_expenses_total',
        'new_capital',
        'new_hkd_balance',
        'settlement_rate',
        'rmb_balance_total',
        'sequence_number',
        'notes',
        'created_by',
        'created_by_type',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'previous_capital' => 'decimal:2',
        'previous_hkd_balance' => 'decimal:2',
        'profit' => 'decimal:3',
        'outgoing_profit' => 'decimal:3',
        'instant_profit' => 'decimal:3',
        'instant_buyout_rate' => 'decimal:5',
        'other_expenses_total' => 'decimal:2',
        'new_capital' => 'decimal:2',
        'new_hkd_balance' => 'decimal:2',
        'settlement_rate' => 'decimal:5',
        'rmb_balance_total' => 'decimal:2',
    ];
    
    /**
     * 追加到模型数组的访问器
     */
    protected $appends = ['creator_name'];

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
     * 关联到本金调整记录
     */
    public function capitalAdjustments()
    {
        return $this->hasMany(CapitalAdjustment::class);
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
     * 关联到执行结余的用户（多态关联）
     * 支持后台管理员（User）和外勤人员（FieldUser）
     */
    public function creator(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by');
    }
    
    /**
     * 获取操作人名称（兼容方法）
     */
    public function getCreatorNameAttribute()
    {
        if (!$this->creator) {
            return '未知';
        }
        
        // FieldUser 有 name 字段，User 只有 username
        if ($this->created_by_type === 'field' && isset($this->creator->name)) {
            return $this->creator->name . ' (外勤)';
        }
        
        return $this->creator->username ?? '未知';
    }

    /**
     * 检查今日是否已结余
     */
    public static function hasSettledToday()
    {
        return static::whereDate('settlement_date', now()->toDateString())->exists();
    }

    /**
     * 获取今日结余记录
     */
    public static function getTodaySettlement()
    {
        return static::whereDate('settlement_date', now()->toDateString())->first();
    }

    /**
     * 按时间范围查询
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('settlement_date', [$startDate, $endDate]);
    }

    /**
     * 按结余日期排序（最新的在前）
     */
    public function scopeOrderByDate($query, $direction = 'desc')
    {
        return $query->orderBy('settlement_date', $direction);
    }
}
