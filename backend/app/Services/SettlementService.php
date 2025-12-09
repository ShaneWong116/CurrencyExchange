<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\SettlementExpense;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\Setting;
use App\Models\BalanceAdjustment;
use App\Models\ChannelBalance;
use App\Models\CurrentStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * 结余服务类
 * 
 * @package App\Services
 */

class SettlementService
{
    /**
     * 检查今日是否已结余
     */
    public function checkTodaySettlement()
    {
        $hasSettled = Settlement::hasSettledToday();
        $settlement = $hasSettled ? Settlement::getTodaySettlement() : null;
        
        return [
            'settled' => $hasSettled,
            'settlement_id' => $settlement ? $settlement->id : null,
            'settlement_date' => $settlement ? $settlement->settlement_date->format('Y-m-d') : null,
        ];
    }

    /**
     * 获取已有结余记录的日期列表(用于前端禁用)
     * 
     * @param int $days 查询最近N天,默认60天
     * @return array ['2024-10-28', '2024-10-30', ...]
     */
    public function getUsedSettlementDates($days = 60): array
    {
        return Settlement::where('settlement_date', '>=', now()->subDays($days))
            ->pluck('settlement_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 推荐结余日期
     * 逻辑:如果今天已有结余,推荐明天;否则推荐今天
     * 
     * @return array ['recommended_date' => '2024-10-29', 'has_today' => true, 'message' => '...']
     */
    public function getRecommendedSettlementDate(): array
    {
        $today = now()->toDateString();
        $hasToday = Settlement::whereDate('settlement_date', $today)->exists();
        
        if ($hasToday) {
            // 今天已有结余,推荐第一个未使用的日期
            $recommendedDate = now()->addDay()->toDateString();
            $count = 1;
            
            // 最多向后查找30天
            while (Settlement::whereDate('settlement_date', $recommendedDate)->exists() && $count < 30) {
                $recommendedDate = now()->addDays(++$count)->toDateString();
            }
            
            return [
                'recommended_date' => $recommendedDate,
                'has_today' => true,
                'message' => "今日已有结余记录,建议选择其他日期"
            ];
        }
        
        return [
            'recommended_date' => $today,
            'has_today' => false,
            'message' => null
        ];
    }

    /**
     * 验证结余确认密码
     */
    public function verifyPassword($password)
    {
        $storedPassword = Setting::get('settlement_password', '');
        
        // 使用 password_verify 验证哈希密码
        return password_verify($password, $storedPassword);
    }

    /**
     * 获取结余预览数据
     * 
     * @param float|null $instantBuyoutRate 即时买断汇率（如有即时买断交易时需要传入）
     */
    public function getPreview($instantBuyoutRate = null)
    {
        $instantBuyoutRate = $instantBuyoutRate !== null ? (float) $instantBuyoutRate : null;

        // 1. 获取当前本金和港币结余
        $currentCapital = BalanceAdjustment::getCurrentCapital();
        $currentHkdBalance = BalanceAdjustment::getCurrentHkdBalance();
        
        // 2. 计算当前各渠道人民币余额汇总
        $channels = Channel::all();
        $rmbBalanceTotal = 0;
        $channelRmbBalances = [];
        foreach ($channels as $channel) {
            $rmbBalance = $channel->getRmbBalance();
            $rmbBalanceTotal += $rmbBalance;
            $channelRmbBalances[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'rmb_balance' => round($rmbBalance, 2),
            ];
        }

        // 3. 获取未结余的入账交易数据（不包括即时买断）
        $unsettledIncomeTransactions = Transaction::unsettled()
            ->where('type', 'income')
            ->get();
        
        $unsettledIncomeRmb = $unsettledIncomeTransactions->sum('rmb_amount');
        $unsettledIncomeHkd = $unsettledIncomeTransactions->sum('hkd_amount');

        // 4. 计算当前结余汇率（成本汇率）
        // 公式：(期初人民币结余 + 当日入账人民币值) ÷ (期初港币结余 + 当日入账港币值)
        
        // 获取期初人民币结余（各渠道 ChannelBalance.initial_amount 之和）
        // 这样可以正确反映结余后的手动余额调整
        $previousRmbBalance = 0;
        foreach ($channels as $channel) {
            $latestBalance = $channel->balances()
                ->where('currency', 'RMB')
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            $previousRmbBalance += $latestBalance ? (float) $latestBalance->initial_amount : 0;
        }
        
        // 期初港币结余
        $previousHkdBalance = $currentHkdBalance;
        
        // 人民币总量 = 期初人民币结余 + 未结余入账人民币金额之和
        $rmbTotal = $previousRmbBalance + $unsettledIncomeRmb;
        
        // 港币总量 = 期初港币结余 + 未结余入账港币金额之和
        $hkdTotal = $previousHkdBalance + $unsettledIncomeHkd;
        
        // 当前结余汇率（成本汇率）= 人民币总量 ÷ 港币总量（保留3位小数）
        $settlementRate = $hkdTotal > 0 ? round($rmbTotal / $hkdTotal, 3, PHP_ROUND_HALF_UP) : 0;

        // 5. 计算出账利润
        $unsettledOutcomeTransactions = Transaction::unsettled()
            ->where('type', 'outcome')
            ->get();
        
        // 出账港币总额
        $outcomeHkdTotal = $unsettledOutcomeTransactions->sum('hkd_amount');
        
        // 出账人民币总额
        $outcomeRmbTotal = $unsettledOutcomeTransactions->sum('rmb_amount');
        
        // 出账港币成本 = 出账人民币总额 ÷ 当前结余汇率(保留2位小数用于计算)
        $outcomeHkdCost = $settlementRate > 0 ? round($outcomeRmbTotal / $settlementRate, 2, PHP_ROUND_HALF_UP) : 0;
        
        // 出账利润 = 出账港币总额 - 出账港币成本(四舍五入到个位)
        $outgoingProfit = round($outcomeHkdTotal - $outcomeHkdCost, 0, PHP_ROUND_HALF_UP);

        // 6. 汇总即时买断利润（直接从交易记录中获取）
        $unsettledInstantTransactions = Transaction::unsettled()
            ->where('type', 'instant_buyout')
            ->get();

        $instantProfit = 0;
        $instantHkdCost = 0; // 折算后的港币实值
        $instantActualHkdTotal = 0;
        $instantRmbTotal = 0;
        $needsInstantRate = false;

        foreach ($unsettledInstantTransactions as $transaction) {
            $rate = $transaction->instant_rate ?? $instantBuyoutRate;

            $instantRmbTotal += $transaction->rmb_amount;
            $instantActualHkdTotal += $transaction->hkd_amount;

            if (!$rate || $rate <= 0) {
                $needsInstantRate = true;
                continue;
            }

            $convertedHkd = round($transaction->rmb_amount / $rate, -1, PHP_ROUND_HALF_UP);
            $instantHkdCost += $convertedHkd;
            $instantProfit += $convertedHkd - $transaction->hkd_amount;
        }

        // 7. 总利润(四舍五入到个位)
        $totalProfit = round($outgoingProfit + $instantProfit, 0, PHP_ROUND_HALF_UP);

        // 计算其他支出为0时的预期结果
        $expectedNewCapital = $currentCapital + $totalProfit;
        $expectedNewHkdBalance = $currentHkdBalance + $unsettledIncomeHkd - $outcomeHkdTotal + $outgoingProfit;

        return [
            // 当前状态（用于核对）
            'previous_capital' => $currentCapital,  // 原本金
            'rmb_balance' => round($rmbBalanceTotal, 2),  // 人民币结余
            'channel_rmb_balances' => $channelRmbBalances,  // 各渠道人民币余额明细
            'total_profit' => $totalProfit,  // 利润（本次结余）
            'new_capital' => round($expectedNewCapital, 2),  // 新本金（结余后）
            
            // 详细计算
            'current_capital' => $currentCapital,
            'current_hkd_balance' => $currentHkdBalance,
            'rmb_balance_total' => round($rmbBalanceTotal, 2),
            'settlement_rate' => $settlementRate,
            
            // 成本汇率计算明细（用于核对）
            'previous_rmb_balance' => round($previousRmbBalance, 2),  // 期初人民币结余
            'previous_hkd_balance' => round($previousHkdBalance, 2),  // 期初港币结余
            'cost_rmb_total' => round($rmbTotal, 2),  // 人民币总量 = 期初 + 入账
            'cost_hkd_total' => round($hkdTotal, 2),  // 港币总量 = 期初 + 入账
            
            // 利润明细
            'outgoing_profit' => $outgoingProfit,
            'instant_profit' => $instantProfit,
            'profit' => $totalProfit,
            
            // 即时买断相关
            'instant_hkd_cost' => round($instantHkdCost, 2),
            'instant_actual_hkd' => round($instantActualHkdTotal, 2),
            'instant_rmb_total' => round($instantRmbTotal, 2),
            'needs_instant_rate' => $needsInstantRate,
            
            // 预期结余后状态（不含其他支出）
            'expected_new_capital' => round($expectedNewCapital, 2),
            'expected_new_hkd_balance' => round($expectedNewHkdBalance, 2),
            
            // 未结余交易统计
            'unsettled_income_count' => $unsettledIncomeTransactions->count(),
            'unsettled_outcome_count' => $unsettledOutcomeTransactions->count(),
            'unsettled_instant_count' => $unsettledInstantTransactions->count(),
            'unsettled_income_rmb' => round($unsettledIncomeRmb, 2),
            'unsettled_income_hkd' => round($unsettledIncomeHkd, 2),
            'unsettled_outcome_rmb' => round($outcomeRmbTotal, 2),
            'unsettled_outcome_hkd' => round($outcomeHkdTotal, 2),
            'outcome_hkd_cost' => $outcomeHkdCost,
            
            // 是否可以执行结余
            'can_settle' => ($unsettledIncomeTransactions->count() + $unsettledOutcomeTransactions->count() + $unsettledInstantTransactions->count()) > 0,
        ];
    }

    /**
     * 执行结余操作
     * 
     * @param string $password 确认密码
     * @param array $expenses 其他支出明细 [['item_name' => '薪金', 'amount' => 100], ...]
     * @param string|null $notes 备注
     * @param int|null $userId 执行结余的用户ID
     * @param string $userType 用户类型: 'admin' 或 'field'
     * @param string|null $settlementDate 结余日期(可选,默认今天)
     * @param float|null $instantBuyoutRate 缺少逐笔汇率时使用的即时买断汇率
     * @return Settlement
     */
    public function execute($password, array $expenses = [], ?string $notes = null, $userId = null, $userType = 'admin', ?string $settlementDate = null, ?float $instantBuyoutRate = null)
    {
        return DB::transaction(function () use ($password, $expenses, $notes, $userId, $userType, $settlementDate, $instantBuyoutRate) {
            // 1. 确定结余日期
            $settlementDate = $settlementDate ?? now()->toDateString();
            
            // 2. 验证日期不能早于今天
            if (Carbon::parse($settlementDate)->isBefore(now()->startOfDay())) {
                throw new Exception('该日期不可用，请选择其他可用日期');
            }
            
            // 3. 检查该日期是否已有结余(给予警告但允许,因为可能需要一天多次结余)
            $existingCount = Settlement::whereDate('settlement_date', $settlementDate)->count();
            if ($existingCount > 0) {
                // 记录日志但不阻止(因为用户已经明确选择了该日期)
                Log::warning("日期 {$settlementDate} 已有 {$existingCount} 条结余记录,用户选择继续");
            }
            
            // 4. 验证密码
            if (!$this->verifyPassword($password)) {
                throw new Exception('确认密码错误');
            }
            
            // 5. 获取预览数据（即时买断利润按新的折算逻辑计算）
            $preview = $this->getPreview($instantBuyoutRate);
            
            // 6. 业务校验
            if (!$preview['can_settle']) {
                throw new Exception('当前没有未结余的交易，无法执行结余操作');
            }
            
            if ($preview['settlement_rate'] <= 0) {
                throw new Exception('结余汇率计算异常，请检查港币结余是否正确');
            }

            if (!empty($preview['needs_instant_rate'])) {
                throw new Exception('存在即时买断交易缺少汇率，请先填写即时买断汇率');
            }
            
            // 7. 计算其他支出总额
            $otherExpensesTotal = 0;
            foreach ($expenses as $expense) {
                $otherExpensesTotal += $expense['amount'] ?? 0;
            }
            
            // 8. 计算结余后的数据
            $newCapital = $preview['current_capital'] + $preview['profit'] - $otherExpensesTotal;
            $newHkdBalance = $preview['current_hkd_balance'] 
                           + $preview['unsettled_income_hkd'] 
                           - $preview['unsettled_outcome_hkd'] 
                           + $preview['outgoing_profit'];
            
            // 9. 获取下一个序号
            $sequenceNumber = Settlement::getNextSequenceNumber();
            
            // 10. 创建结余记录(使用指定的日期)
            $settlement = Settlement::create([
                'settlement_date' => $settlementDate,  // 使用传入的日期
                'previous_capital' => $preview['current_capital'],
                'previous_hkd_balance' => $preview['current_hkd_balance'],
                'profit' => $preview['profit'],
                'outgoing_profit' => $preview['outgoing_profit'],
                'instant_profit' => $preview['instant_profit'],
                'instant_buyout_rate' => $instantBuyoutRate,
                'other_expenses_total' => $otherExpensesTotal,
                'new_capital' => $newCapital,
                'new_hkd_balance' => $newHkdBalance,
                'settlement_rate' => $preview['settlement_rate'],
                'rmb_balance_total' => $preview['rmb_balance_total'],
                'sequence_number' => $sequenceNumber,
                'notes' => $notes,
                'created_by' => $userId,
                'created_by_type' => $userType, // 保存用户类型
            ]);
            
            // 11. 保存其他支出明细
            if (!empty($expenses)) {
                foreach ($expenses as $expense) {
                    SettlementExpense::create([
                        'settlement_id' => $settlement->id,
                        'item_name' => $expense['item_name'],
                        'amount' => $expense['amount'],
                    ]);
                }
            }
            
            // 12. 固化各渠道人民币余额到 ChannelBalance 的 initial_amount
            // 这一步必须在标记交易为已结算之前执行，因为 getRmbBalance() 依赖未结算交易
            $today = Carbon::today();
            foreach ($preview['channel_rmb_balances'] as $channelBalance) {
                $balance = ChannelBalance::firstOrCreate([
                    'channel_id' => $channelBalance['id'],
                    'currency' => 'RMB',
                    'date' => $today,
                ], [
                    'initial_amount' => 0,
                    'income_amount' => 0,
                    'outcome_amount' => 0,
                    'current_balance' => 0,
                ]);
                
                // 将当前余额固化为新的基础余额
                $balance->initial_amount = $channelBalance['rmb_balance'];
                $balance->current_balance = $channelBalance['rmb_balance'];
                $balance->save();
                
                Log::info("Settlement: Channel {$channelBalance['id']} RMB balance fixed to {$channelBalance['rmb_balance']}");
            }
            
            // 13. 更新所有未结余的交易状态
            Transaction::unsettled()->update([
                'settlement_status' => 'settled',
                'settlement_id' => $settlement->id,
                'settlement_date' => $settlement->settlement_date,
            ]);
            
            // 14. 清空当前统计表(因为所有交易都已结算)
            CurrentStatistic::clearAll();
            
            // 15. 创建本金调整记录（结算类型）
            BalanceAdjustment::createCapitalAdjustment(
                $newCapital,
                'settlement',
                sprintf(
                    '结算调整 - 结算号: %s, 利润: HK$ %s, 其他支出: HK$ %s',
                    $sequenceNumber,
                    number_format($preview['profit'], 2),
                    number_format($otherExpensesTotal, 2)
                ),
                $settlement->id,
                $userId
            );
            
            // 16. 创建港币余额调整记录（结算类型）
            BalanceAdjustment::createHkdBalanceAdjustment(
                afterAmount: $newHkdBalance,
                adjustmentType: 'settlement',
                reason: sprintf(
                    '结算调整 - 结算号: %s, 入账: +HK$ %s, 出账: -HK$ %s, 出账利润: +HK$ %s',
                    $sequenceNumber,
                    number_format($preview['unsettled_income_hkd'], 2),
                    number_format($preview['unsettled_outcome_hkd'], 2),
                    number_format($preview['outgoing_profit'], 2)
                ),
                settlementId: $settlement->id,
                userId: $userId
            );
            
            return $settlement;
        });
    }

    /**
     * 获取结余详情
     */
    public function getDetail($settlementId)
    {
        $settlement = Settlement::with('expenses', 'transactions', 'creator')->findOrFail($settlementId);
        
        return [
            'settlement' => $settlement,
            'expenses' => $settlement->expenses,
            'transactions_count' => $settlement->transactions->count(),
            'income_transactions_count' => $settlement->transactions->where('type', 'income')->count(),
            'outcome_transactions_count' => $settlement->transactions->where('type', 'outcome')->count(),
            'instant_buyout_transactions_count' => $settlement->transactions->where('type', 'instant_buyout')->count(),
            'creator_name' => $settlement->creator_name, // 使用模型的 accessor
        ];
    }

    /**
     * 获取结余历史列表（按日期排序）
     */
    public function getHistory($page = 1, $perPage = 20)
    {
        return Settlement::with('expenses', 'creator')
            ->orderByDate('desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
