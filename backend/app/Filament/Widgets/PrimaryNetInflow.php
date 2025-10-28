<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\Widget;

class PrimaryNetInflow extends Widget
{
    protected static ?string $heading = null;
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.primary-net-inflow';

    protected function getViewData(): array
    {
        $today = today();

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
            'rmbNet' => $todayRmbIncome - $todayRmbOutcome,
            'rmbIncome' => $todayRmbIncome,
            'rmbOutcome' => $todayRmbOutcome,
            // 港币方向与人民币相反：入账记为流出、出账记为流入
            'hkdNet' => $todayHkdOutcome - $todayHkdIncome,
            'hkdIncome' => $todayHkdIncome,
            'hkdOutcome' => $todayHkdOutcome,
        ];
    }
}


