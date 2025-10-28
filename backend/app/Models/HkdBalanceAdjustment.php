<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HkdBalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'before_amount',
        'after_amount',
        'adjustment_amount',
        'adjustment_type',
        'settlement_id',
        'user_id',
        'reason'
    ];

    protected $casts = [
        'before_amount' => 'decimal:2',
        'after_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
    ];

    /**
     * 关联结算记录
     */
    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    /**
     * 关联操作用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取当前港币余额
     */
    public static function getCurrentBalance(): float
    {
        // 优先从调整记录获取最新余额
        $latestAdjustment = static::orderBy('created_at', 'desc')->first();
        
        if ($latestAdjustment) {
            return (float) $latestAdjustment->after_amount;
        }
        
        // 如果没有调整记录，从系统设置获取
        return (float) Setting::get('hkd_balance', 0);
    }

    /**
     * 创建港币余额调整记录
     */
    public static function createAdjustment(
        float $afterAmount,
        string $adjustmentType = 'manual',
        ?string $reason = null,
        ?int $settlementId = null,
        ?int $userId = null
    ): self {
        $beforeAmount = static::getCurrentBalance();
        $adjustmentAmount = $afterAmount - $beforeAmount;

        $adjustment = static::create([
            'before_amount' => $beforeAmount,
            'after_amount' => $afterAmount,
            'adjustment_amount' => $adjustmentAmount,
            'adjustment_type' => $adjustmentType,
            'settlement_id' => $settlementId,
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        // 同步更新系统设置中的港币余额
        Setting::set('hkd_balance', $afterAmount, '港币结余(HKD)', 'number');

        return $adjustment;
    }

    /**
     * Scope: 手动调整
     */
    public function scopeManual($query)
    {
        return $query->where('adjustment_type', 'manual');
    }

    /**
     * Scope: 结算调整
     */
    public function scopeSettlement($query)
    {
        return $query->where('adjustment_type', 'settlement');
    }

    /**
     * Scope: 系统调整
     */
    public function scopeSystem($query)
    {
        return $query->where('adjustment_type', 'system');
    }

    /**
     * 判断是否为增加
     */
    public function isIncrease(): bool
    {
        return $this->adjustment_amount > 0;
    }

    /**
     * 判断是否为减少
     */
    public function isDecrease(): bool
    {
        return $this->adjustment_amount < 0;
    }
}

