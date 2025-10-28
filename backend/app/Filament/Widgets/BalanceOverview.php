<?php

namespace App\Filament\Widgets;

use App\Models\Setting;
use App\Models\Channel;
use App\Models\CapitalAdjustment;
use App\Models\HkdBalanceAdjustment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BalanceOverview extends BaseWidget
{
    protected static ?int $sort = 2;
    
    // 禁用轮询，避免缓存问题
    protected static ?string $pollingInterval = null;
    
    protected function getColumns(): int
    {
        return 3;
    }
    
    protected function getStats(): array
    {
        // 获取本金（从本金调整记录中获取最新值）
        $capital = CapitalAdjustment::getCurrentCapital();
        
        // 获取港币结余（从港币余额调整记录中获取最新值，确保数据准确）
        $hkdBalance = HkdBalanceAdjustment::getCurrentBalance();
        
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
                ->chart([7, 4, 5, 6, 7, 8, 9])
                ->url(\App\Filament\Resources\CapitalAdjustmentResource::getUrl('index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition'
                ]),
                
            Stat::make('人民币余额', '¥' . number_format($rmbBalance, 2))
                ->description('各渠道余额汇总')
                ->descriptionIcon('heroicon-m-currency-yen')
                ->color('success')
                ->chart([4, 5, 6, 7, 6, 7, 8])
                ->url(\App\Filament\Resources\BalanceAdjustmentResource::getUrl('index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition'
                ]),
                
            Stat::make('港币余额', 'HK$' . number_format($hkdBalance, 2))
                ->description('当前港币结余')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->chart([5, 6, 5, 7, 8, 7, 9])
                ->url(\App\Filament\Resources\BalanceAdjustmentResource::getUrl('index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition'
                ]),
        ];
    }
}

