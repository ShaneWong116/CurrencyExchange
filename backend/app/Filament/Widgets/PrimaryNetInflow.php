<?php

namespace App\Filament\Widgets;

use App\Models\CurrentStatistic;
use App\Models\Transaction;
use Filament\Widgets\Widget;

class PrimaryNetInflow extends Widget
{
    protected static ?string $heading = null;
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.primary-net-inflow';

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

    protected function getViewData(): array
    {
        $locationId = $this->getLocationId();

        if ($locationId) {
            // 按地点筛选时,直接查询交易表
            $baseQuery = Transaction::whereDate('created_at', today())
                ->where('location_id', $locationId);

            $rmbIncome = (clone $baseQuery)->where('type', 'income')->sum('rmb_amount');
            $rmbOutcome = (clone $baseQuery)->where('type', 'outcome')->sum('rmb_amount');
            $rmbInstantBuyout = (clone $baseQuery)->where('transaction_label', '即时买断')->sum('rmb_amount');

            $hkdIncome = (clone $baseQuery)->where('type', 'outcome')->sum('hkd_amount');
            $hkdOutcome = (clone $baseQuery)->where('type', 'income')->sum('hkd_amount');
            $hkdInstantBuyout = (clone $baseQuery)->where('transaction_label', '即时买断')->sum('hkd_amount');

            $todayRmbIncome = $rmbIncome + $rmbInstantBuyout;
            $todayRmbOutcome = $rmbOutcome + $rmbInstantBuyout;
            $todayHkdIncome = $hkdIncome + $hkdInstantBuyout;
            $todayHkdOutcome = $hkdOutcome + $hkdInstantBuyout;
        } else {
            // 总览时,从统计表直接读取
            $stats = CurrentStatistic::getDashboardStats();

            // 人民币入账 = income + instant_buyout
            $todayRmbIncome = $stats['rmb_income'] + $stats['rmb_instant_buyout'];
            
            // 人民币出账 = outcome + instant_buyout  
            $todayRmbOutcome = $stats['rmb_outcome'] + $stats['rmb_instant_buyout'];

            // 港币入账 = outcome（方向相反）+ instant_buyout
            $todayHkdIncome = $stats['hkd_outcome'] + $stats['hkd_instant_buyout'];
            
            // 港币出账 = income（方向相反）+ instant_buyout
            $todayHkdOutcome = $stats['hkd_income'] + $stats['hkd_instant_buyout'];
        }

        return [
            'rmbNet' => $todayRmbIncome - $todayRmbOutcome,
            'rmbIncome' => $todayRmbIncome,
            'rmbOutcome' => $todayRmbOutcome,
            // 港币方向与人民币相反：入账记为流出、出账记为流入
            'hkdNet' => $todayHkdIncome - $todayHkdOutcome,
            'hkdIncome' => $todayHkdOutcome,
            'hkdOutcome' => $todayHkdIncome,
        ];
    }
}



