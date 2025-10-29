<?php

namespace App\Filament\Widgets;

use App\Models\CurrentStatistic;
use App\Models\Transaction;
use App\Models\TransactionDraft;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    // 接收父页面传递的 location filter
    public ?string $locationFilter = 'all';

    protected $listeners = [
        'locationFilterChanged' => 'updateLocationFilter',
    ];

    public function updateLocationFilter($locationId): void
    {
        $this->locationFilter = $locationId;
    }

    protected function getLocationId(): ?int
    {
        return $this->locationFilter === 'all' ? null : (int) $this->locationFilter;
    }
    
    protected function getColumns(): int
    {
        return 4;
    }
    
    protected function getStats(): array
    {
        $locationId = $this->getLocationId();

        if ($locationId) {
            // 按地点筛选时,直接查询交易表(只查询未结算的)
            $todayQuery = Transaction::where('settlement_status', 'unsettled')
                ->where('location_id', $locationId);

            $todayTransactions = (clone $todayQuery)->count();
            $todayIncome = (clone $todayQuery)->where('type', 'income')->count();
            $todayOutcome = (clone $todayQuery)->where('type', 'outcome')->count();
            $todayInstantBuyout = (clone $todayQuery)->where('type', 'instant_buyout')->count();

            // 草稿数量（按用户的地点筛选）
            $totalDrafts = TransactionDraft::whereHas('user', function($query) use ($locationId) {
                $query->where('location_id', $locationId);
            })->count();

            // 本月金额统计（按地点筛选,只统计未结算的）
            $monthlyQuery = Transaction::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('settlement_status', 'unsettled')
                ->where('location_id', $locationId);

            $monthlyRmb = (clone $monthlyQuery)->sum('rmb_amount');
            $monthlyHkd = (clone $monthlyQuery)->sum('hkd_amount');
        } else {
            // 总览时,从统计表获取今日数据
            $stats = CurrentStatistic::getDashboardStats();
            
            $todayTransactions = $stats['transaction_count'];
            $todayIncome = $stats['income_count'];
            $todayOutcome = $stats['outcome_count'];
            $todayInstantBuyout = $stats['instant_buyout_count'];
            
            // 草稿数量（不频繁变化，保留原查询）
            $totalDrafts = TransactionDraft::count();
            
            // 本月金额统计（需要单独查询，因为统计表只记录当前周期,只统计未结算的）
            $monthlyRmb = Transaction::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('settlement_status', 'unsettled')
                ->sum('rmb_amount');
            $monthlyHkd = Transaction::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('settlement_status', 'unsettled')
                ->sum('hkd_amount');
        }

        return [
            Stat::make('今日交易', $todayTransactions)
                ->description("入账: {$todayIncome} | 出账: {$todayOutcome} | 即时买断: {$todayInstantBuyout}")
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
        ];
    }
}