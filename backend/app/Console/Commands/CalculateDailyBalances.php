<?php

namespace App\Console\Commands;

use App\Services\BalanceService;
use Illuminate\Console\Command;

class CalculateDailyBalances extends Command
{
    protected $signature = 'balance:calculate-daily';
    protected $description = '计算每日渠道余额';

    public function handle(BalanceService $balanceService)
    {
        $this->info('开始计算每日余额...');
        
        try {
            $balanceService->calculateTodayBalances();
            $this->info('每日余额计算完成！');
            
        } catch (\Exception $e) {
            $this->error('每日余额计算失败: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}
