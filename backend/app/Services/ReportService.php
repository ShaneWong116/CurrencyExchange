<?php

namespace App\Services;

use App\Models\BalanceCarryForward;
use App\Models\Channel;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * 生成指定日期的日结数据
     */
    public function generateDailySettlement(string $date): array
    {
        $theDate = Carbon::parse($date)->startOfDay();
        $yesterday = $theDate->copy()->subDay()->toDateString();

        $channels = Channel::active()->get();

        $rows = [];
        $totalBalance = 0.0;
        $totalProfit = 0.0;

        foreach ($channels as $channel) {
            $yesterdayBalance = BalanceCarryForward::where('channel_id', $channel->id)
                ->whereDate('date', $yesterday)
                ->value('balance_cny') ?? 0.0;

            $todayIncomeRmb = (float) Transaction::where('channel_id', $channel->id)
                ->where('type', 'income')
                ->whereDate('created_at', $theDate)
                ->sum('rmb_amount');

            $todayOutcomeRmb = (float) Transaction::where('channel_id', $channel->id)
                ->where('type', 'outcome')
                ->whereDate('created_at', $theDate)
                ->sum('rmb_amount');

            $todayIncomeHkd = (float) Transaction::where('channel_id', $channel->id)
                ->where('type', 'income')
                ->whereDate('created_at', $theDate)
                ->sum('hkd_amount');

            $todayOutcomeHkd = (float) Transaction::where('channel_id', $channel->id)
                ->where('type', 'outcome')
                ->whereDate('created_at', $theDate)
                ->sum('hkd_amount');

            $currentBalance = $yesterdayBalance + $todayIncomeRmb - $todayOutcomeRmb;
            // 利润（港币）按需求：出账 - 入账
            $profit = $todayOutcomeHkd - $todayIncomeHkd;

            $rows[] = [
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'yesterday_balance' => round($yesterdayBalance, 2),
                'today_income_cny' => round($todayIncomeRmb, 2),
                'today_expense_cny' => round($todayOutcomeRmb, 2),
                'current_balance' => round($currentBalance, 2),
                'today_income_hkd' => round($todayIncomeHkd, 2),
                'today_expense_hkd' => round($todayOutcomeHkd, 2),
                'profit' => round($profit, 2),
            ];

            $totalBalance += $currentBalance;
            $totalProfit += $profit;
        }

        return [
            'channels' => $rows,
            'total_balance' => round($totalBalance, 2),
            'total_profit' => round($totalProfit, 2),
        ];
    }

    /**
     * 将日结结果写入结余记录表（作为次日的昨日结余）
     */
    public function persistDailyCarryForward(string $date, array $dailyData): void
    {
        DB::transaction(function () use ($date, $dailyData) {
            foreach ($dailyData['channels'] as $row) {
                BalanceCarryForward::updateOrCreate(
                    [
                        'channel_id' => $row['channel_id'],
                        'date' => $date,
                    ],
                    [
                        'balance_cny' => $row['current_balance'],
                    ]
                );
            }
        });
    }

    /**
     * 生成指定年-月的月结数据
     */
    public function generateMonthlySettlement(int $year, int $month, array $otherExpenses = []): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $rows = [];
        $totalOther = 0.0;

        // 汇总传入的其他支出
        foreach ($otherExpenses as $item) {
            $totalOther += (float) ($item['amount_hkd'] ?? 0);
        }

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $yesterday = $date->copy()->subDay()->toDateString();

            $principal = (float) BalanceCarryForward::whereDate('date', $yesterday)
                ->sum('balance_cny');

            $incomeHkd = (float) Transaction::whereDate('created_at', $date)
                ->where('type', 'income')
                ->sum('hkd_amount');

            $outcomeHkd = (float) Transaction::whereDate('created_at', $date)
                ->where('type', 'outcome')
                ->sum('hkd_amount');

            $profit = $incomeHkd - $outcomeHkd;

            $rows[] = [
                'date' => $date->toDateString(),
                'principal' => round($principal, 2),
                'total_income' => round($incomeHkd, 2),
                'total_expense' => round($outcomeHkd, 2),
                'profit' => round($profit, 2),
            ];
        }

        // 利润改为（支出 - 收入），总利润为各日利润之和 + 其他支出
        $totalProfit = array_sum(array_column($rows, 'profit')) + $totalOther;

        return [
            'monthly_data' => $rows,
            'other_expenses' => round($totalOther, 2),
            'final_profit' => round($totalProfit, 2),
        ];
    }

    /**
     * 生成指定年份的年结数据
     */
    public function generateYearlySettlement(int $year, array $otherExpenses = []): array
    {
        $rows = [];
        $totalOther = 0.0;
        foreach ($otherExpenses as $item) {
            $totalOther += (float) ($item['amount_hkd'] ?? 0);
        }

        for ($m = 1; $m <= 12; $m++) {
            $monthStart = Carbon::create($year, $m, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $prevDay = $monthStart->copy()->subDay()->toDateString();
            $principal = (float) BalanceCarryForward::whereDate('date', $prevDay)
                ->sum('balance_cny');

            $incomeHkd = (float) Transaction::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('type', 'income')
                ->sum('hkd_amount');

            $outcomeHkd = (float) Transaction::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('type', 'outcome')
                ->sum('hkd_amount');

            // 利润（港币）按需求：出账 - 入账
            $profit = $outcomeHkd - $incomeHkd;

            $rows[] = [
                'month' => $monthStart->format('Y-m'),
                'principal' => round($principal, 2),
                'total_income' => round($incomeHkd, 2),
                'total_expense' => round($outcomeHkd, 2),
                'profit' => round($profit, 2),
            ];
        }

        // 利润改为（支出 - 收入），总利润为各月利润之和 + 其他支出
        $totalProfit = array_sum(array_column($rows, 'profit')) + $totalOther;

        return [
            'yearly_data' => $rows,
            'other_expenses' => round($totalOther, 2),
            'final_profit' => round($totalProfit, 2),
        ];
    }
}


