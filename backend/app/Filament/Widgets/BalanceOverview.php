<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Models\Channel;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BalanceOverview extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected function getColumns(): int
    {
        return 3;
    }
    
    protected function getStats(): array
    {
        // 获取本金（从系统设置中获取）
        $capital = Setting::get('capital', 0);
        
        // 获取港币结余（从系统设置中获取）
        $hkdBalance = Setting::get('hkd_balance', 0);
        
        // 计算人民币余额（各渠道人民币余额汇总）
        $rmbBalance = Channel::where('status', 'active')
            ->get()
            ->sum(function ($channel) {
                return $channel->getRmbBalance();
            });

        return [
            Stat::make('本金', 'HK$' . number_format($capital, 2))
                ->description('当前系统本金')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart([7, 4, 5, 6, 7, 8, 9]),
                
            Stat::make('人民币余额', '¥' . number_format($rmbBalance, 2))
                ->description('各渠道余额汇总')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('success')
                ->chart([4, 5, 6, 7, 6, 7, 8]),
                
            Stat::make('港币余额', 'HK$' . number_format($hkdBalance, 2))
                ->description('当前港币结余')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->chart([5, 6, 5, 7, 8, 7, 9]),
        ];
    }
}

