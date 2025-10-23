<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionSummary extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $activeTab = request()->query('activeTab', 'all');

        $base = Transaction::query();

        if ($activeTab === 'today') {
            $base = $base->whereDate('created_at', today());
        }

        // 统计收入与支出（人民币/港币分别求和）
        $incomeQuery = (clone $base)->where('type', 'income');
        $outcomeQuery = (clone $base)->where('type', 'outcome');

        $incomeRmb = (float) $incomeQuery->sum('rmb_amount');
        $incomeHkd = (float) $incomeQuery->sum('hkd_amount');

        $outcomeRmb = (float) $outcomeQuery->sum('rmb_amount');
        $outcomeHkd = (float) $outcomeQuery->sum('hkd_amount');

        return [
            Stat::make('收入-人民币', '¥' . number_format($incomeRmb, 2))
                ->description('人民币合计')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('收入-港币', 'HK$' . number_format($incomeHkd, 2))
                ->description('港币合计')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('支出-人民币', '¥' . number_format($outcomeRmb, 2))
                ->description('人民币合计')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),

            Stat::make('支出-港币', 'HK$' . number_format($outcomeHkd, 2))
                ->description('港币合计')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),
        ];
    }
}


