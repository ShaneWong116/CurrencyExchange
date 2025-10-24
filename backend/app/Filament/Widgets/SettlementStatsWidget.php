<?php

namespace App\Filament\Widgets;

use App\Models\Settlement;
use App\Services\SettlementService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SettlementStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $settlementService = app(SettlementService::class);
        $preview = $settlementService->getPreview();
        
        $lastSettlement = Settlement::latest('created_at')->first();
        
        return [
            Stat::make('当前结余汇率', number_format($preview['settlement_rate'], 3))
                ->description('人民币/港币汇率')
                ->descriptionIcon('heroicon-o-calculator')
                ->color('info'),
            
            Stat::make('预计利润', number_format($preview['profit'], 2) . ' HKD')
                ->description('基于未结余交易计算')
                ->descriptionIcon($preview['profit'] >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($preview['profit'] >= 0 ? 'success' : 'danger'),
            
            Stat::make('未结余交易', ($preview['unsettled_income_count'] + $preview['unsettled_outcome_count']) . ' 笔')
                ->description('入账: ' . $preview['unsettled_income_count'] . ' | 出账: ' . $preview['unsettled_outcome_count'])
                ->descriptionIcon('heroicon-o-document-text')
                ->color('warning'),
            
            Stat::make('上次结余时间', $lastSettlement ? $lastSettlement->created_at->format('Y-m-d H:i') : '暂无记录')
                ->description($lastSettlement ? "序号 #{$lastSettlement->sequence_number}" : '')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
        ];
    }
}

