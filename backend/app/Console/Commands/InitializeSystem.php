<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BalanceService;
use App\Models\Channel;
use App\Models\ChannelBalance;
use Carbon\Carbon;

class InitializeSystem extends Command
{
    protected $signature = 'system:initialize';
    protected $description = '初始化系统数据';

    public function handle(BalanceService $balanceService)
    {
        $this->info('开始初始化系统...');

        try {
            // 1. 初始化渠道余额
            $this->info('初始化渠道余额...');
            $this->initializeChannelBalances();

            // 2. 计算当前余额
            $this->info('计算今日余额...');
            $balanceService->calculateTodayBalances();

            $this->info('✅ 系统初始化完成！');

        } catch (\Exception $e) {
            $this->error('❌ 系统初始化失败: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function initializeChannelBalances()
    {
        $channels = Channel::active()->get();
        $today = Carbon::today();

        foreach ($channels as $channel) {
            foreach (['RMB', 'HKD'] as $currency) {
                ChannelBalance::firstOrCreate([
                    'channel_id' => $channel->id,
                    'currency' => $currency,
                    'date' => $today,
                ], [
                    'initial_amount' => 0,
                    'income_amount' => 0,
                    'outcome_amount' => 0,
                    'current_balance' => 0,
                ]);
            }
        }

        $this->info('渠道余额初始化完成');
    }
}
