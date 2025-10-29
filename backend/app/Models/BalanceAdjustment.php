<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_category',
        'settlement_id',
        'channel_id',
        'user_id',
        'currency',
        'before_amount',
        'adjustment_amount',
        'after_amount',
        'type',
        'reason'
    ];

    protected $casts = [
        'before_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'after_amount' => 'decimal:2',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function scopeByChannel($query, $channelId)
    {
        return $query->where('channel_id', $channelId);
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function isIncrease()
    {
        return $this->adjustment_amount > 0;
    }

    public function isDecrease()
    {
        return $this->adjustment_amount < 0;
    }

    // 分类作用域
    public function scopeCapital($query)
    {
        return $query->where('adjustment_category', 'capital');
    }

    public function scopeChannel($query)
    {
        return $query->where('adjustment_category', 'channel');
    }

    public function scopeHkdBalance($query)
    {
        return $query->where('adjustment_category', 'hkd_balance');
    }

    // 静态方法：获取当前本金
    public static function getCurrentCapital(): float
    {
        $latest = static::capital()->latest('created_at')->first();
        
        if (!$latest) {
            return (float) Setting::get('system_capital_hkd', 0);
        }
        
        return (float) $latest->after_amount;
    }

    // 静态方法：获取当前港币余额
    public static function getCurrentHkdBalance(): float
    {
        $latest = static::hkdBalance()->latest('created_at')->first();
        
        if (!$latest) {
            return (float) Setting::get('hkd_balance', 0);
        }
        
        return (float) $latest->after_amount;
    }

    // 静态方法：创建本金调整
    public static function createCapitalAdjustment(
        float $newAmount,
        string $type,
        string $reason,
        ?int $settlementId = null,
        ?int $userId = null
    ): self {
        $currentCapital = static::getCurrentCapital();
        $adjustmentAmount = $newAmount - $currentCapital;

        return static::create([
            'adjustment_category' => 'capital',
            'before_amount' => $currentCapital,
            'after_amount' => $newAmount,
            'adjustment_amount' => $adjustmentAmount,
            'type' => $type,
            'settlement_id' => $settlementId,
            'user_id' => $userId ?? auth()->id(),
            'reason' => $reason,
            'currency' => 'HKD',
        ]);
    }

    // 静态方法：创建港币余额调整
    public static function createHkdBalanceAdjustment(
        float $afterAmount,
        string $adjustmentType = 'manual',
        ?string $reason = null,
        ?int $settlementId = null,
        ?int $userId = null
    ): self {
        $beforeAmount = static::getCurrentHkdBalance();
        $adjustmentAmount = $afterAmount - $beforeAmount;

        $adjustment = static::create([
            'adjustment_category' => 'hkd_balance',
            'before_amount' => $beforeAmount,
            'after_amount' => $afterAmount,
            'adjustment_amount' => $adjustmentAmount,
            'type' => $adjustmentType,
            'settlement_id' => $settlementId,
            'user_id' => $userId ?? auth()->id(),
            'reason' => $reason,
            'currency' => 'HKD',
        ]);

        // 同步更新系统设置中的港币余额
        Setting::set('hkd_balance', $afterAmount, '港币结余(HKD)', 'number');

        return $adjustment;
    }
}
