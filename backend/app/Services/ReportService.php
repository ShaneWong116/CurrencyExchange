<?php

namespace App\Services;

use App\Models\BalanceCarryForward;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\Settlement;
use App\Models\SettlementExpense;
use App\Models\BalanceAdjustment;
use App\Models\QuarterlyDividend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportService
{
    /**
     * 生成指定日期的日结数据
     * 注意：直接从 ChannelBalance 表读取实时余额，不再依赖历史计算
     */
    public function generateDailySettlement(string $date): array
    {
        $theDate = Carbon::parse($date)->startOfDay();

        $channels = Channel::active()->get();

        $rows = [];
        $totalBalance = 0.0;
        $totalProfit = 0.0;

        foreach ($channels as $channel) {
            // 直接读取渠道当前的实时余额（从 ChannelBalance 表）
            $currentBalance = $channel->getRmbBalance();

            // 统计今日交易（用于展示明细，不用于计算余额）
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

            // 昨日余额 = 当前余额 - 今日净变化
            $yesterdayBalance = $currentBalance - $todayIncomeRmb + $todayOutcomeRmb;
            
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
                'income' => null,
                'income_items' => [],
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
            $start,
            $end
        ])
        ->with('expenses')
        ->orderBy('settlement_date', 'asc')
        ->get();

        // 填充实际结余数据
        foreach ($settlements as $settlement) {
            $date = Carbon::parse($settlement->settlement_date);
            $dateString = $date->format('Y.n.j');
            
            if (isset($dailyData[$dateString])) {
                // 获取收入明细（type='income' 的支出项）
                $incomeItems = $settlement->expenses->filter(fn($e) => $e->type === 'income');
                
                $dailyData[$dateString] = [
                    'date' => $dateString,
                    'full_date' => $settlement->settlement_date,
                    'settlement_date' => $settlement->settlement_date->format('Y-m-d'),
                    'previous_capital' => (float) $settlement->previous_capital,
                    'profit' => (float) ($settlement->outgoing_profit + $settlement->instant_profit),
                    'outgoing_profit' => (float) $settlement->outgoing_profit,
                    'instant_profit' => (float) $settlement->instant_profit,
                    'income' => (float) $settlement->other_incomes_total,
                    'income_items' => $incomeItems->map(fn($e) => [
                        'name' => $e->item_name,
                        'amount' => (float) $e->amount,
                    ])->toArray(),
                    'expenses' => (float) $settlement->other_expenses_total,
                    'expense_items' => $settlement->expenses->filter(fn($e) => $e->type !== 'income')->map(fn($e) => [
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
        $totalIncome = 0;
        $totalExpenses = 0;
        $incomeBreakdown = [];
        $expenseBreakdown = [];

        foreach ($dailyData as $day) {
            if ($day['has_settlement']) {
                $totalProfit += $day['profit'] ?? 0;
                $totalIncome += $day['income'] ?? 0;
                $totalExpenses += $day['expenses'] ?? 0;
                
                // 汇总收入明细
                foreach ($day['income_items'] ?? [] as $item) {
                    if (!isset($incomeBreakdown[$item['name']])) {
                        $incomeBreakdown[$item['name']] = 0;
                    }
                    $incomeBreakdown[$item['name']] += $item['amount'];
                }
                
                // 汇总支出明细
                foreach ($day['expense_items'] as $item) {
                    if (!isset($expenseBreakdown[$item['name']])) {
                        $expenseBreakdown[$item['name']] = 0;
                    }
                    $expenseBreakdown[$item['name']] += $item['amount'];
                }
            }
        }

        $netProfit = $totalProfit + $totalIncome - $totalExpenses;

        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'daily_data' => array_values($dailyData),
            'summary' => [
                'total_profit' => round($totalProfit, 2),
                'total_income' => round($totalIncome, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'income_breakdown' => $incomeBreakdown,
                'expense_breakdown' => $expenseBreakdown,
            ],
        ];
    }

    /**
     * 获取月度收支明细数据
     * @param int $year 年份
     * @param int $month 月份
     * @return array 包含收入和支出明细的数组
     */
    public function getMonthlyIncomeExpenseDetail(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $end->day;

        // 查询该月的所有结算记录 - 使用whereYear和whereMonth确保正确匹配
        $settlements = Settlement::whereYear('settlement_date', $year)
            ->whereMonth('settlement_date', $month)
            ->with('expenses')
            ->orderBy('settlement_date', 'asc')
            ->get();

        // 初始化收入数据数组（包含所有日期）
        $incomeData = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Carbon::create($year, $month, $day);
            $dateString = $date->toDateString();
            $dateDisplay = $date->format('Y.n.j');
            
            // 查找该日期的结算记录 - 使用回调函数正确比较Carbon日期
            $settlement = $settlements->first(function($s) use ($dateString) {
                return $s->settlement_date->toDateString() === $dateString;
            });
            
            if ($settlement) {
                // 基础利润（出账利润 + 即时买断利润）
                $baseProfit = (float) ($settlement->outgoing_profit + $settlement->instant_profit);
                
                // 获取该结算的"其他收入"项目（type = 'income'）
                $otherIncomes = $settlement->expenses->filter(function($expense) {
                    return $expense->type === 'income';
                });
                
                // 计算其他收入总额
                $otherIncomeTotal = $otherIncomes->sum('amount');
                
                // 实际总利润 = 基础利润 + 其他收入
                $totalProfit = $baseProfit + (float) $otherIncomeTotal;
                
                // 构建项目名称列表
                $items = [];
                
                // 如果有基础利润（出账利润+即时买断利润），显示"日结算利润"
                if ($baseProfit > 0) {
                    $items[] = '日结算利润';
                }
                
                // 添加其他收入的项目名称
                foreach ($otherIncomes as $income) {
                    $items[] = $income->item_name;
                }
                
                // 如果没有任何项目，显示"-"
                $itemsDisplay = count($items) > 0 ? implode('、', $items) : '-';
                
                $incomeData[] = [
                    'date' => $dateString,
                    'date_display' => $dateDisplay,
                    'outgoing_profit' => (float) $settlement->outgoing_profit,
                    'instant_profit' => (float) $settlement->instant_profit,
                    'base_profit' => $baseProfit,
                    'other_income' => (float) $otherIncomeTotal,
                    'total_profit' => $totalProfit,
                    'items' => $itemsDisplay,
                    'has_settlement' => true,
                    'settlement_id' => $settlement->id,
                    'remarks' => $settlement->notes ?? ''
                ];
            } else {
                // 无结算数据的日期，显示0
                $incomeData[] = [
                    'date' => $dateString,
                    'date_display' => $dateDisplay,
                    'outgoing_profit' => 0.0,
                    'instant_profit' => 0.0,
                    'base_profit' => 0.0,
                    'other_income' => 0.0,
                    'total_profit' => 0.0,
                    'items' => '-',
                    'has_settlement' => false,
                    'settlement_id' => null,
                    'remarks' => ''
                ];
            }
        }

        // 查询支出数据（仅包含有数据的日期）
        $expenseData = [];
        foreach ($settlements as $settlement) {
            // 获取该结算的其他支出项目（type != 'income'）
            $expenses = $settlement->expenses->filter(function($expense) {
                return $expense->type !== 'income';
            });

            foreach ($expenses as $expense) {
                $expenseData[] = [
                    'id' => $expense->id,
                    'date' => $settlement->settlement_date->toDateString(),
                    'date_display' => $settlement->settlement_date->format('Y.n.j'),
                    'amount' => (float) $expense->amount,
                    'item_name' => $expense->item_name,
                    'remarks' => $expense->remarks ?? '',
                    'settlement_id' => $settlement->id
                ];
            }
        }

        // 计算汇总统计
        $totalIncome = array_sum(array_column($incomeData, 'total_profit'));
        $totalExpenses = array_sum(array_column($expenseData, 'amount'));
        $totalProfit = $totalIncome - $totalExpenses;

        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $daysInMonth,
            'income_data' => $incomeData,
            'expense_data' => $expenseData,
            'summary' => [
                'total_income' => round($totalIncome, 2),
                'total_expenses' => round($totalExpenses, 2),
                'total_profit' => round($totalProfit, 2)
            ]
        ];
    }

    /**
     * 更新支出项目备注
     * @param int $expenseId 支出项目ID
     * @param string $remark 新备注
     * @return array 包含成功状态和错误信息的数组
     */
    public function updateSettlementExpenseRemark(int $expenseId, string $remark): array
    {
        try {
            // 数据验证
            $validator = \Validator::make([
                'expense_id' => $expenseId,
                'remark' => $remark
            ], [
                'expense_id' => 'required|integer|min:1',
                'remark' => 'nullable|string|max:1000', // 限制备注长度为1000字符
            ], [
                'expense_id.required' => '支出项目ID不能为空',
                'expense_id.integer' => '支出项目ID必须为整数',
                'expense_id.min' => '支出项目ID必须大于0',
                'remark.string' => '备注必须为文本格式',
                'remark.max' => '备注长度不能超过1000个字符',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray()
                ];
            }

            // 查找支出记录
            $expense = SettlementExpense::find($expenseId);
            if (!$expense) {
                return [
                    'success' => false,
                    'message' => '支出记录不存在',
                    'errors' => ['expense_id' => ['支出记录不存在']]
                ];
            }

            // 安全过滤：移除HTML标签和恶意脚本
            $filteredRemark = $this->sanitizeInput($remark);

            // 更新备注
            $expense->remarks = $filteredRemark;
            $success = $expense->save();

            if ($success) {
                \Log::info('Settlement expense remark updated successfully', [
                    'expense_id' => $expenseId,
                    'old_remark' => $expense->getOriginal('remarks'),
                    'new_remark' => $filteredRemark,
                    'user_id' => auth()->id() ?? 'system'
                ]);

                return [
                    'success' => true,
                    'message' => '备注更新成功',
                    'data' => [
                        'expense_id' => $expenseId,
                        'remark' => $filteredRemark
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '数据库更新失败，请稍后重试',
                    'errors' => ['database' => ['数据库更新失败']]
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Failed to update settlement expense remark', [
                'expense_id' => $expenseId,
                'remark' => $remark,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '系统错误，请联系管理员',
                'errors' => ['system' => ['系统内部错误']]
            ];
        }
    }

    /**
     * 安全过滤输入内容
     * @param string|null $input 输入内容
     * @return string 过滤后的内容
     */
    private function sanitizeInput(?string $input): string
    {
        if (empty($input)) {
            return '';
        }

        // 移除HTML标签
        $filtered = strip_tags($input);
        
        // 转义特殊字符防止XSS
        $filtered = htmlspecialchars($filtered, ENT_QUOTES, 'UTF-8');
        
        // 移除多余的空白字符
        $filtered = trim($filtered);
        
        // 移除连续的空白字符
        $filtered = preg_replace('/\s+/', ' ', $filtered);
        
        return $filtered;
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

    /**
     * 获取年度报表数据
     * @param int $year 年份
     * @return array 包含年度汇总和每月明细的数组
     */
    public function getYearlyReportData(int $year): array
    {
        $monthlyData = [];
        $totalIncome = 0;
        $totalExpenses = 0;
        $totalProfit = 0;
        $finalCapital = 0;

        // 获取季度分红数据
        $dividends = QuarterlyDividend::where('year', $year)
            ->get()
            ->keyBy('month');

        // 遍历12个月
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth();

            // 查询该月的所有结算记录
            $settlements = Settlement::whereYear('settlement_date', $year)
                ->whereMonth('settlement_date', $month)
                ->with('expenses')
                ->orderBy('settlement_date', 'asc')
                ->get();

            // 计算该月的收入、支出、利润
            $monthIncome = 0;
            $monthExpenses = 0;
            $monthProfit = 0;
            $monthCapital = 0;

            if ($settlements->count() > 0) {
                // 获取该月最后一天有数据的结算记录，使用其 new_capital 作为月度本金
                $lastSettlement = $settlements->last();
                $monthCapital = (float) $lastSettlement->new_capital;

                foreach ($settlements as $settlement) {
                    // 基础利润（出账利润 + 即时买断利润）
                    $baseProfit = (float) ($settlement->outgoing_profit + $settlement->instant_profit);
                    
                    // 其他收入
                    $otherIncome = (float) $settlement->other_incomes_total;
                    
                    // 总收入 = 基础利润 + 其他收入
                    $monthIncome += $baseProfit + $otherIncome;
                    
                    // 支出
                    $monthExpenses += (float) $settlement->other_expenses_total;
                }
                
                // 月利润 = 收入 - 支出
                $monthProfit = $monthIncome - $monthExpenses;
                
                // 更新年度总本金为最后有数据的月份的本金
                $finalCapital = $monthCapital;
            }

            // 获取季度分红
            $dividend = isset($dividends[$month]) ? (float) $dividends[$month]->amount : 0;

            $monthlyData[] = [
                'month' => $month,
                'month_display' => $month . '月',
                'capital' => round($monthCapital, 2),
                'income' => round($monthIncome, 2),
                'expenses' => round($monthExpenses, 2),
                'profit' => round($monthProfit, 2),
                'dividend' => round($dividend, 2),
                'has_data' => $settlements->count() > 0,
            ];

            $totalIncome += $monthIncome;
            $totalExpenses += $monthExpenses;
            $totalProfit += $monthProfit;
        }

        return [
            'year' => $year,
            'monthly_data' => $monthlyData,
            'summary' => [
                'total_income' => round($totalIncome, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($totalProfit, 2),
                'final_capital' => round($finalCapital, 2),
            ],
        ];
    }

    /**
     * 更新季度分红
     * @param int $year 年份
     * @param int $month 月份
     * @param float $amount 分红金额
     * @return array 包含成功状态和错误信息的数组
     */
    public function updateQuarterlyDividend(int $year, int $month, float $amount): array
    {
        try {
            // 数据验证
            $validator = \Validator::make([
                'year' => $year,
                'month' => $month,
                'amount' => $amount
            ], [
                'year' => 'required|integer|min:2000|max:2100',
                'month' => 'required|integer|min:1|max:12',
                'amount' => 'required|numeric|min:0',
            ], [
                'year.required' => '年份不能为空',
                'year.integer' => '年份必须为整数',
                'year.min' => '年份不能小于2000',
                'year.max' => '年份不能大于2100',
                'month.required' => '月份不能为空',
                'month.integer' => '月份必须为整数',
                'month.min' => '月份必须在1-12之间',
                'month.max' => '月份必须在1-12之间',
                'amount.required' => '分红金额不能为空',
                'amount.numeric' => '分红金额必须为数字',
                'amount.min' => '分红金额不能为负数',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()->toArray()
                ];
            }

            // 更新或创建季度分红记录
            $dividend = QuarterlyDividend::updateOrCreate(
                ['year' => $year, 'month' => $month],
                ['amount' => $amount]
            );

            \Log::info('Quarterly dividend updated successfully', [
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'user_id' => auth()->id() ?? 'system'
            ]);

            return [
                'success' => true,
                'message' => '季度分红更新成功',
                'data' => [
                    'year' => $year,
                    'month' => $month,
                    'amount' => $amount
                ]
            ];

        } catch (\Exception $e) {
            \Log::error('Failed to update quarterly dividend', [
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '系统错误，请联系管理员',
                'errors' => ['system' => ['系统内部错误']]
            ];
        }
    }
}
