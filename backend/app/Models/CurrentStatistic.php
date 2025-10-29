<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrentStatistic extends Model
{
    protected $fillable = [
        'stat_type',
        'reference_id',
        'transaction_count',
        'income_count',
        'outcome_count',
        'instant_buyout_count',
        'rmb_income',
        'rmb_outcome',
        'rmb_instant_buyout',
        'hkd_income',
        'hkd_outcome',
        'hkd_instant_buyout',
    ];

    protected $casts = [
        'rmb_income' => 'decimal:2',
        'rmb_outcome' => 'decimal:2',
        'rmb_instant_buyout' => 'decimal:2',
        'hkd_income' => 'decimal:2',
        'hkd_outcome' => 'decimal:2',
        'hkd_instant_buyout' => 'decimal:2',
    ];

    /**
     * 获取或创建统计记录
     */
    public static function getOrCreate(string $statType, ?int $referenceId = null): self
    {
        return self::firstOrCreate(
            [
                'stat_type' => $statType,
                'reference_id' => $referenceId,
            ],
            [
                'transaction_count' => 0,
                'income_count' => 0,
                'outcome_count' => 0,
                'instant_buyout_count' => 0,
                'rmb_income' => 0,
                'rmb_outcome' => 0,
                'rmb_instant_buyout' => 0,
                'hkd_income' => 0,
                'hkd_outcome' => 0,
                'hkd_instant_buyout' => 0,
            ]
        );
    }

    /**
     * 增加交易统计
     */
    public function addTransaction(Transaction $transaction): void
    {
        $this->increment('transaction_count');

        // 根据交易类型更新计数
        match ($transaction->type) {
            'income' => $this->increment('income_count'),
            'outcome' => $this->increment('outcome_count'),
            'instant_buyout' => $this->increment('instant_buyout_count'),
            default => null,
        };

        // 更新金额统计
        if ($transaction->type === 'instant_buyout') {
            $this->increment('rmb_instant_buyout', $transaction->rmb_amount);
            $this->increment('hkd_instant_buyout', $transaction->hkd_amount);
        } else {
            if ($transaction->type === 'income') {
                $this->increment('rmb_income', $transaction->rmb_amount);
                $this->increment('hkd_income', $transaction->hkd_amount);
            } elseif ($transaction->type === 'outcome') {
                $this->increment('rmb_outcome', $transaction->rmb_amount);
                $this->increment('hkd_outcome', $transaction->hkd_amount);
            }
        }
    }

    /**
     * 减少交易统计（删除交易时使用）
     */
    public function removeTransaction(Transaction $transaction): void
    {
        $this->decrement('transaction_count');

        // 根据交易类型更新计数
        match ($transaction->type) {
            'income' => $this->decrement('income_count'),
            'outcome' => $this->decrement('outcome_count'),
            'instant_buyout' => $this->decrement('instant_buyout_count'),
            default => null,
        };

        // 更新金额统计
        if ($transaction->type === 'instant_buyout') {
            $this->decrement('rmb_instant_buyout', $transaction->rmb_amount);
            $this->decrement('hkd_instant_buyout', $transaction->hkd_amount);
        } else {
            if ($transaction->type === 'income') {
                $this->decrement('rmb_income', $transaction->rmb_amount);
                $this->decrement('hkd_income', $transaction->hkd_amount);
            } elseif ($transaction->type === 'outcome') {
                $this->decrement('rmb_outcome', $transaction->rmb_amount);
                $this->decrement('hkd_outcome', $transaction->hkd_amount);
            }
        }
    }

    /**
     * 清空所有统计（结算后调用）
     */
    public static function clearAll(): void
    {
        self::query()->delete();
    }

    /**
     * 获取仪表盘统计数据
     */
    public static function getDashboardStats(): array
    {
        $stats = self::where('stat_type', 'dashboard')->first();

        if (!$stats) {
            return [
                'transaction_count' => 0,
                'income_count' => 0,
                'outcome_count' => 0,
                'instant_buyout_count' => 0,
                'rmb_income' => 0,
                'rmb_outcome' => 0,
                'rmb_instant_buyout' => 0,
                'hkd_income' => 0,
                'hkd_outcome' => 0,
                'hkd_instant_buyout' => 0,
            ];
        }

        return [
            'transaction_count' => $stats->transaction_count,
            'income_count' => $stats->income_count,
            'outcome_count' => $stats->outcome_count,
            'instant_buyout_count' => $stats->instant_buyout_count,
            'rmb_income' => (float) $stats->rmb_income,
            'rmb_outcome' => (float) $stats->rmb_outcome,
            'rmb_instant_buyout' => (float) $stats->rmb_instant_buyout,
            'hkd_income' => (float) $stats->hkd_income,
            'hkd_outcome' => (float) $stats->hkd_outcome,
            'hkd_instant_buyout' => (float) $stats->hkd_instant_buyout,
        ];
    }

    /**
     * 获取渠道统计数据
     */
    public static function getChannelStats(int $channelId): array
    {
        $stats = self::where('stat_type', 'channel')
            ->where('reference_id', $channelId)
            ->first();

        if (!$stats) {
            return [
                'transaction_count' => 0,
                'rmb_income' => 0,
                'rmb_outcome' => 0,
                'rmb_instant_buyout' => 0,
                'hkd_income' => 0,
                'hkd_outcome' => 0,
                'hkd_instant_buyout' => 0,
            ];
        }

        return [
            'transaction_count' => $stats->transaction_count,
            'rmb_income' => (float) $stats->rmb_income,
            'rmb_outcome' => (float) $stats->rmb_outcome,
            'rmb_instant_buyout' => (float) $stats->rmb_instant_buyout,
            'hkd_income' => (float) $stats->hkd_income,
            'hkd_outcome' => (float) $stats->hkd_outcome,
            'hkd_instant_buyout' => (float) $stats->hkd_instant_buyout,
        ];
    }
}
