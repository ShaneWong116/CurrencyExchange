<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\ChannelBalance;
use App\Models\Transaction;
use App\Models\BalanceAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceService
{
    /**
     * 计算并更新所有渠道的今日余额
     */
    public function calculateTodayBalances()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        
        DB::beginTransaction();
        
        try {
            $channels = Channel::active()->get();
            
            foreach ($channels as $channel) {
                $this->calculateChannelDailyBalance($channel, $today, $yesterday);
            }
            
            DB::commit();
            Log::info('Daily balance calculation completed successfully');
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Daily balance calculation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 计算指定渠道的每日余额
     */
    public function calculateChannelDailyBalance(Channel $channel, Carbon $date, Carbon $previousDate)
    {
        foreach (['RMB', 'HKD'] as $currency) {
            $this->calculateChannelCurrencyBalance($channel, $currency, $date, $previousDate);
        }
    }
    
    /**
     * 计算指定渠道指定货币的余额
     */
    private function calculateChannelCurrencyBalance(Channel $channel, string $currency, Carbon $date, Carbon $previousDate)
    {
        // 获取昨天的余额作为今天的初始余额
        $previousBalance = ChannelBalance::where('channel_id', $channel->id)
            ->where('currency', $currency)
            ->where('date', $previousDate)
            ->first();
            
        $initialAmount = $previousBalance ? $previousBalance->current_balance : 0;
        
        // 计算今日交易（分别记录原始入/出账额，另计算按方向规则的净变动）
        $amountField = $currency === 'RMB' ? 'rmb_amount' : 'hkd_amount';

        $todayIncomeAmount = Transaction::where('channel_id', $channel->id)
            ->where('type', 'income')
            ->whereDate('created_at', $date)
            ->sum($amountField);

        $todayOutcomeAmount = Transaction::where('channel_id', $channel->id)
            ->where('type', 'outcome')
            ->whereDate('created_at', $date)
            ->sum($amountField);

        // 入账/出账方向：入账 RMB+、HKD-；出账 RMB-、HKD+
        if ($currency === 'RMB') {
            $netExpr = 'SUM(CASE WHEN type = "income" THEN rmb_amount WHEN type = "outcome" THEN -rmb_amount ELSE 0 END) as net';
        } else { // HKD
            $netExpr = 'SUM(CASE WHEN type = "income" THEN -hkd_amount WHEN type = "outcome" THEN hkd_amount ELSE 0 END) as net';
        }

        $todayNetChange = (float) Transaction::where('channel_id', $channel->id)
            ->whereDate('created_at', $date)
            ->selectRaw($netExpr)
            ->value('net');

        // 计算当前余额（昨日余额 + 今日净变动）
        $currentBalance = $initialAmount + $todayNetChange;
        
        // 更新或创建余额记录
        ChannelBalance::updateOrCreate([
            'channel_id' => $channel->id,
            'currency' => $currency,
            'date' => $date,
        ], [
            'initial_amount' => $initialAmount,
            'income_amount' => $todayIncomeAmount,
            'outcome_amount' => $todayOutcomeAmount,
            'current_balance' => $currentBalance,
        ]);
    }
    
    /**
     * 手动调整余额
     */
    public function adjustBalance(
        int $channelId, 
        string $currency, 
        float $adjustmentAmount, 
        string $reason, 
        int $userId = null
    ): BalanceAdjustment {
        DB::beginTransaction();
        
        try {
            $channel = Channel::findOrFail($channelId);
            $userId = $userId ?: auth()->id();
            
            // 获取当前余额
            $currentBalance = $channel->getCurrentBalance($currency);
            
            // 创建调整记录
            $adjustment = BalanceAdjustment::create([
                'channel_id' => $channelId,
                'user_id' => $userId,
                'currency' => $currency,
                'before_amount' => $currentBalance,
                'adjustment_amount' => $adjustmentAmount,
                'after_amount' => $currentBalance + $adjustmentAmount,
                'type' => 'manual',
                'reason' => $reason,
            ]);
            
            // 更新今日余额
            $today = Carbon::today();
            $balance = ChannelBalance::getOrCreateTodayBalance($channelId, $currency, $currentBalance);
            $balance->current_balance = $adjustment->after_amount;
            $balance->save();
            
            DB::commit();
            
            Log::info("Balance adjustment completed", [
                'channel_id' => $channelId,
                'currency' => $currency,
                'adjustment_amount' => $adjustmentAmount,
                'user_id' => $userId,
            ]);
            
            return $adjustment;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Balance adjustment failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取渠道余额历史
     */
    public function getBalanceHistory(int $channelId, string $currency, int $days = 30): array
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days - 1);
        
        $balances = ChannelBalance::where('channel_id', $channelId)
            ->where('currency', $currency)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();
            
        $history = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $balance = $balances->firstWhere('date', $date->toDateString());
            $history[] = [
                'date' => $date->toDateString(),
                'balance' => $balance ? $balance->current_balance : 0,
                'income' => $balance ? $balance->income_amount : 0,
                'outcome' => $balance ? $balance->outcome_amount : 0,
            ];
        }
        
        return $history;
    }
    
    /**
     * 重新计算指定日期范围的余额
     */
    public function recalculateBalances(Carbon $startDate, Carbon $endDate, int $channelId = null)
    {
        DB::beginTransaction();
        
        try {
            $channels = $channelId ? [Channel::findOrFail($channelId)] : Channel::active()->get();
            
            for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
                $previousDate = $date->copy()->subDay();
                
                foreach ($channels as $channel) {
                    $this->calculateChannelDailyBalance($channel, $date, $previousDate);
                }
            }
            
            DB::commit();
            Log::info("Balance recalculation completed", [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'channel_id' => $channelId,
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Balance recalculation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取所有渠道当前余额总览
     * 注意：需要统计所有渠道（包括停用的），因为停用渠道可能仍有余额
     */
    public function getAllChannelsBalanceOverview(): array
    {
        $channels = Channel::all();
        $overview = [
            'total_rmb' => 0,
            'total_hkd' => 0,
            'channels' => [],
        ];
        
        foreach ($channels as $channel) {
            $rmbBalance = $channel->getRmbBalance();
            $hkdBalance = $channel->getHkdBalance();
            
            $overview['total_rmb'] += $rmbBalance;
            $overview['total_hkd'] += $hkdBalance;
            
            $overview['channels'][] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'rmb_balance' => $rmbBalance,
                'hkd_balance' => $hkdBalance,
            ];
        }
        
        return $overview;
    }
}
