<?php

namespace App\Services;

use App\Models\BalanceCarryForward;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\Settlement;
use App\Models\SettlementExpense;
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

    /**
     * 获取指定年月的月度结余数据（用于嵌套表格显示）
     */
    public function getMonthlySettlementData(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $end->day;

        // 初始化每日数据数组
        $dailyData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateString = $date->format('Y.n.j');
            
            $dailyData[$dateString] = [
                'date' => $dateString,
                'full_date' => $date->toDateString(),
                'settlement_date' => $date->format('Y-m-d'), // 添加标准格式日期
                'previous_capital' => null,
                'profit' => null,
                'outgoing_profit' => null,
                'instant_profit' => null,
                'expenses' => null,
                'expense_items' => [],
                'new_capital' => null,
                'rmb_balance' => null,
                'hkd_balance' => null,
                'notes' => null,
                'has_settlement' => false,
            ];
        }

        // 查询该月的所有结余记录
        $settlements = Settlement::whereBetween('settlement_date', [
            $start->toDateString(),
            $end->toDateString()
        ])
        ->with('expenses')
        ->orderBy('settlement_date', 'asc')
        ->get();

        // 填充实际结余数据
        foreach ($settlements as $settlement) {
            $date = Carbon::parse($settlement->settlement_date);
            $dateString = $date->format('Y.n.j');
            
            if (isset($dailyData[$dateString])) {
                $dailyData[$dateString] = [
                    'date' => $dateString,
                    'full_date' => $settlement->settlement_date,
                    'settlement_date' => $settlement->settlement_date->format('Y-m-d'),
                    'previous_capital' => (float) $settlement->previous_capital,
                    'profit' => (float) ($settlement->outgoing_profit + $settlement->instant_profit),
                    'outgoing_profit' => (float) $settlement->outgoing_profit,
                    'instant_profit' => (float) $settlement->instant_profit,
                    'expenses' => (float) $settlement->other_expenses_total,
                    'expense_items' => $settlement->expenses->map(fn($e) => [
                        'name' => $e->item_name,
                        'amount' => (float) $e->amount,
                    ])->toArray(),
                    'new_capital' => (float) $settlement->new_capital,
                    'rmb_balance' => (float) $settlement->rmb_balance_total,
                    'hkd_balance' => (float) $settlement->new_hkd_balance,
                    'notes' => $settlement->notes,
                    'has_settlement' => true,
                ];
            }
        }

        // 计算汇总数据
        $totalProfit = 0;
        $totalExpenses = 0;
        $expenseBreakdown = [];

        foreach ($dailyData as $day) {
            if ($day['has_settlement']) {
                $totalProfit += $day['profit'] ?? 0;
                $totalExpenses += $day['expenses'] ?? 0;
                
                // 汇总支出明细
                foreach ($day['expense_items'] as $item) {
                    if (!isset($expenseBreakdown[$item['name']])) {
                        $expenseBreakdown[$item['name']] = 0;
                    }
                    $expenseBreakdown[$item['name']] += $item['amount'];
                }
            }
        }

        $netProfit = $totalProfit - $totalExpenses;

        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'daily_data' => array_values($dailyData),
            'summary' => [
                'total_income' => round($totalProfit, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'expense_breakdown' => $expenseBreakdown,
            ],
        ];
    }

    /**
     * 获取指定日期的交易明细数据（日报表）
     * 显示结算日期为当天的已结算交易
     */
    public function getDailyTransactionData(string $date): array
    {
        $theDate = Carbon::parse($date);

        // 查询当天的结余记录
        $settlement = Settlement::whereDate('settlement_date', $theDate->toDateString())->first();

        // 查询结算日期为当天的已结算交易
        $transactions = Transaction::with(['user', 'channel', 'location'])
            ->whereDate('settlement_date', $theDate->toDateString())
            ->where('settlement_status', 'settled')
            ->orderBy('created_at', 'desc')
            ->get();

        // 按交易类型分组统计
        // 先尝试用 transaction_label，如果为空则根据 type 判断
        $incomeTransactions = $transactions->filter(function ($t) {
            if ($t->transaction_label) {
                return $t->transaction_label === 'income';
            }
            // income = 入账
            return $t->type === 'income';
        });
        
        $outgoingTransactions = $transactions->filter(function ($t) {
            if ($t->transaction_label) {
                return $t->transaction_label === 'outgoing';
            }
            // outcome = 出账
            return $t->type === 'outcome';
        });
        
        $instantTransactions = $transactions->filter(function ($t) {
            if ($t->transaction_label) {
                return $t->transaction_label === 'instant_buyout';
            }
            // 即时买断需要 type='instant_buyout'
            return $t->type === 'instant_buyout';
        });

        // 统计汇总
        $incomeCount = $incomeTransactions->count();
        $incomeRmbTotal = $incomeTransactions->sum('rmb_amount');
        $incomeHkdTotal = $incomeTransactions->sum('hkd_amount');
        
        $outgoingCount = $outgoingTransactions->count();
        $outgoingRmbTotal = $outgoingTransactions->sum('rmb_amount');
        $outgoingHkdTotal = $outgoingTransactions->sum('hkd_amount');
        
        $instantCount = $instantTransactions->count();
        $instantRmbTotal = $instantTransactions->sum('rmb_amount');
        $instantHkdTotal = $instantTransactions->sum('hkd_amount');

        $totalCount = $transactions->count();
        $totalRmbAmount = $transactions->sum('rmb_amount');
        $totalHkdAmount = $transactions->sum('hkd_amount');

        // 格式化交易记录
        $transactionList = $transactions->map(function ($transaction) {
            // 判断交易标签：优先使用 transaction_label，其次根据 type 判断
            $label = $transaction->transaction_label;
            if (!$label) {
                // 如果 transaction_label 为空，根据 type 判断
                if ($transaction->type === 'income') {
                    $label = 'income'; // 入账
                } elseif ($transaction->type === 'outcome') {
                    $label = 'outgoing'; // 出账
                } elseif ($transaction->type === 'instant_buyout') {
                    $label = 'instant_buyout'; // 即时买断
                } else {
                    $label = 'unknown';
                }
            }
            
            // 根据 label 显示文本
            $labelTextMap = [
                'income' => '入账',
                'outgoing' => '出账',
                'instant_buyout' => '即时买断',
                'unknown' => '未知'
            ];
            
            return [
                'id' => $transaction->id,
                'uuid' => $transaction->uuid,
                'type' => $transaction->type,
                'label' => $label,
                'label_text' => $labelTextMap[$label] ?? '未知',
                'rmb_amount' => (float) $transaction->rmb_amount,
                'hkd_amount' => (float) $transaction->hkd_amount,
                'exchange_rate' => round((float) $transaction->exchange_rate, 3), // 保留三位小数
                'instant_rate' => $transaction->instant_rate ? round((float) $transaction->instant_rate, 3) : null,
                'channel' => $transaction->channel ? $transaction->channel->name : '-',
                'channel_id' => $transaction->channel_id,
                'location' => $transaction->location ? $transaction->location->name : $transaction->location,
                'user' => $transaction->user ? $transaction->user->name : '-',
                'notes' => $transaction->notes,
                'status' => $transaction->status,
                'settlement_status' => $transaction->settlement_status,
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'), // 显示完整日期时间
                'created_at_full' => $transaction->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return [
            'date' => $theDate->format('Y-m-d'),
            'date_display' => $theDate->format('Y年n月j日'),
            'day_of_week' => $theDate->locale('zh_CN')->dayName,
            'has_settlement' => $settlement !== null,
            'settlement' => $settlement ? [
                'previous_capital' => (float) $settlement->previous_capital,
                'outgoing_profit' => (float) $settlement->outgoing_profit,
                'instant_profit' => (float) $settlement->instant_profit,
                'total_profit' => (float) ($settlement->outgoing_profit + $settlement->instant_profit),
                'expenses' => (float) $settlement->other_expenses_total,
                'expense_items' => $settlement->expenses->map(fn($e) => [
                    'name' => $e->item_name,
                    'amount' => (float) $e->amount,
                ])->toArray(),
                'new_capital' => (float) $settlement->new_capital,
                'new_hkd_balance' => (float) $settlement->new_hkd_balance,
                'rmb_balance_total' => (float) $settlement->rmb_balance_total,
                'settlement_rate' => (float) $settlement->settlement_rate,
                'notes' => $settlement->notes,
            ] : null,
            'summary' => [
                'total_count' => $totalCount,
                'total_rmb_amount' => round($totalRmbAmount, 2),
                'total_hkd_amount' => round($totalHkdAmount, 2),
                'income_count' => $incomeCount,
                'income_rmb_total' => round($incomeRmbTotal, 2),
                'income_hkd_total' => round($incomeHkdTotal, 2),
                'outgoing_count' => $outgoingCount,
                'outgoing_rmb_total' => round($outgoingRmbTotal, 2),
                'outgoing_hkd_total' => round($outgoingHkdTotal, 2),
                'instant_count' => $instantCount,
                'instant_rmb_total' => round($instantRmbTotal, 2),
                'instant_hkd_total' => round($instantHkdTotal, 2),
            ],
            'transactions' => $transactionList,
        ];
    }
}


