<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Models\TransactionDraft;
use App\Models\FieldUser;
use App\Models\Channel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getColumns(): int
    {
        return 4;
    }
    
    protected function getStats(): array
    {
        $today = today();
        
        // 今日交易统计
        $todayTransactions = Transaction::whereDate('created_at', $today)->count();
        $todayIncome = Transaction::whereDate('created_at', $today)
            ->where('type', 'income')->count();
        $todayOutcome = Transaction::whereDate('created_at', $today)
            ->where('type', 'outcome')->count();
        $todayExchange = Transaction::whereDate('created_at', $today)
            ->where('type', 'exchange')->count();
            
        // 总计统计
        $totalTransactions = Transaction::count();
        $totalDrafts = TransactionDraft::count();
        $activeUsers = FieldUser::where('status', 'active')->count();
        $activeChannels = Channel::where('status', 'active')->count();
        
        // 本月金额统计
        $monthlyRmb = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('rmb_amount');
        $monthlyHkd = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('hkd_amount');

        // 今日金额统计
        $todayRmbIncome = Transaction::whereDate('created_at', $today)
            ->where('type', 'income')
            ->sum('rmb_amount');
        $todayRmbOutcome = Transaction::whereDate('created_at', $today)
            ->where('type', 'outcome')
            ->sum('rmb_amount');
        $todayHkdIncome = Transaction::whereDate('created_at', $today)
            ->where('type', 'income')
            ->sum('hkd_amount');
        $todayHkdOutcome = Transaction::whereDate('created_at', $today)
            ->where('type', 'outcome')
            ->sum('hkd_amount');

        return [
            // 仅保留四个次要统计
            Stat::make('今日交易', $todayTransactions)
                ->description("入账: {$todayIncome} | 出账: {$todayOutcome} | 兑换: {$todayExchange}")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Stat::make('草稿数量', $totalDrafts)
                ->description('待提交草稿')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
                
            Stat::make('本月人民币', '¥' . number_format($monthlyRmb, 2))
                ->description('本月交易总额')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('info'),
                
            Stat::make('本月港币', 'HK$' . number_format($monthlyHkd, 2))
                ->description('本月交易总额')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),
            // 可按地点扩展：在 LocationOverview 小部件中实现
        ];
    }
}