<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CapitalAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'before_amount',
        'after_amount',
        'adjustment_amount',
        'adjustment_type',
        'settlement_id',
        'user_id',
        'reason',
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
     * 获取调整类型显示名称
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->adjustment_type) {
            'manual' => '手动调整',
            'settlement' => '结算调整',
            'system' => '系统调整',
            default => $this->adjustment_type,
        };
    }

    /**
     * 获取当前系统本金（最新的 after_amount）
     */
    public static function getCurrentCapital(): float
    {
        $latest = static::latest('created_at')->first();
        
        if (!$latest) {
            // 如果没有记录，从设置中获取初始本金
            return (float) Setting::get('system_capital_hkd', 0);
        }
        
        return (float) $latest->after_amount;
    }

    /**
     * 创建本金调整记录
     */
    public static function createAdjustment(
        float $newAmount,
        string $type,
        string $reason,
        ?int $settlementId = null,
        ?int $userId = null
    ): self {
        $currentCapital = static::getCurrentCapital();
        $adjustmentAmount = $newAmount - $currentCapital;

        return static::create([
            'before_amount' => $currentCapital,
            'after_amount' => $newAmount,
            'adjustment_amount' => $adjustmentAmount,
            'adjustment_type' => $type,
            'settlement_id' => $settlementId,
            'user_id' => $userId ?? auth()->id(),
            'reason' => $reason,
        ]);
    }
}

