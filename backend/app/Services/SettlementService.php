<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\SettlementExpense;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\Setting;
use App\Models\BalanceAdjustment;
use Illuminate\Support\Facades\DB;
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
        // 1. 获取当前本金和港币结余
        $currentCapital = BalanceAdjustment::getCurrentCapital();
        $currentHkdBalance = BalanceAdjustment::getCurrentHkdBalance();
        
        // 2. 计算当前各渠道人民币余额汇总
        $channels = Channel::all();
        $rmbBalanceTotal = 0;
        foreach ($channels as $channel) {
            $rmbBalanceTotal += $channel->getRmbBalance();
        }

        // 3. 获取未结余的入账交易数据（不包括即时买断）
        $unsettledIncomeTransactions = Transaction::unsettled()
            ->where('type', 'income')
            ->get();
        
        $unsettledIncomeRmb = $unsettledIncomeTransactions->sum('rmb_amount');
        $unsettledIncomeHkd = $unsettledIncomeTransactions->sum('hkd_amount');

        // 4. 计算当前结余汇率
        // 修正说明:渠道余额汇总已经包含了出账的影响(入账-出账)
        // 但结余汇率应该只基于未结余的入账交易,不应该受出账影响
        // 正确计算:只使用未结余的入账交易
        
        // 人民币总量 = 未结余入账人民币金额之和
        $rmbTotal = $unsettledIncomeRmb;
        
        // 港币总量 = 未结余入账港币金额之和  
        $hkdTotal = $unsettledIncomeHkd;
        
        // 当前结余汇率 = 人民币总量 ÷ 港币总量（保留3位小数）
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
        
        // 即时买断利润 = 所有未结余即时买断交易的利润之和
        $instantProfit = $unsettledInstantTransactions->sum('instant_profit');
        $instantHkdCost = $unsettledInstantTransactions->sum('hkd_amount');
        $instantRmbTotal = $unsettledInstantTransactions->sum('rmb_amount');

        // 7. 总利润(四舍五入到个位)
        $totalProfit = round($outgoingProfit + $instantProfit, 0, PHP_ROUND_HALF_UP);

        // 计算其他支出为0时的预期结果
        $expectedNewCapital = $currentCapital + $totalProfit;
        $expectedNewHkdBalance = $currentHkdBalance + $totalProfit;

        return [
            // 当前状态（用于核对）
            'previous_capital' => $currentCapital,  // 原本金
            'rmb_balance' => round($rmbBalanceTotal, 2),  // 人民币结余
            'total_profit' => $totalProfit,  // 利润（本次结余）
            'new_capital' => round($expectedNewCapital, 2),  // 新本金（结余后）
            
            // 详细计算
            'current_capital' => $currentCapital,
            'current_hkd_balance' => $currentHkdBalance,
            'rmb_balance_total' => round($rmbBalanceTotal, 2),
            'settlement_rate' => $settlementRate,
            
            // 利润明细
            'outgoing_profit' => $outgoingProfit,
            'instant_profit' => $instantProfit,
            'profit' => $totalProfit,
            
            // 即时买断相关
            'instant_hkd_cost' => round($instantHkdCost, 2),
            'instant_rmb_total' => round($instantRmbTotal, 2),
            
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
     * @return Settlement
     */
    public function execute($password, array $expenses = [], ?string $notes = null, $userId = null, $userType = 'admin')
    {
        return DB::transaction(function () use ($password, $expenses, $notes, $userId, $userType) {
            // 1. 检查今日是否已结余
            if (Settlement::hasSettledToday()) {
                throw new Exception('今日已完成结余，无法重复操作');
            }
            
            // 2. 验证密码
            if (!$this->verifyPassword($password)) {
                throw new Exception('确认密码错误');
            }
            
            // 3. 获取预览数据（即时买断利润已在录入时计算）
            $preview = $this->getPreview();
            
            // 4. 业务校验
            if (!$preview['can_settle']) {
                throw new Exception('当前没有未结余的交易，无法执行结余操作');
            }
            
            if ($preview['settlement_rate'] <= 0) {
                throw new Exception('结余汇率计算异常，请检查港币结余是否正确');
            }
            
            // 5. 计算其他支出总额
            $otherExpensesTotal = 0;
            foreach ($expenses as $expense) {
                $otherExpensesTotal += $expense['amount'] ?? 0;
            }
            
            // 6. 计算结余后的数据
            $newCapital = $preview['current_capital'] + $preview['profit'] - $otherExpensesTotal;
            $newHkdBalance = $preview['current_hkd_balance'] + $preview['profit'];
            // $newHkdBalance = $preview['current_hkd_balance'] + $preview['outgoing_profit'] - $otherExpensesTotal;
            
            // 7. 获取下一个序号
            $sequenceNumber = Settlement::getNextSequenceNumber();
            
            // 8. 创建结余记录
            $settlement = Settlement::create([
                'settlement_date' => now()->toDateString(),
                'previous_capital' => $preview['current_capital'],
                'previous_hkd_balance' => $preview['current_hkd_balance'],
                'profit' => $preview['profit'],
                'outgoing_profit' => $preview['outgoing_profit'],
                'instant_profit' => $preview['instant_profit'],
                'instant_buyout_rate' => null, // 不再使用统一汇率，每笔交易已记录自己的利润
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
            
            // 9. 保存其他支出明细
            if (!empty($expenses)) {
                foreach ($expenses as $expense) {
                    SettlementExpense::create([
                        'settlement_id' => $settlement->id,
                        'item_name' => $expense['item_name'],
                        'amount' => $expense['amount'],
                    ]);
                }
            }
            
            // 10. 更新所有未结余的交易状态
            Transaction::unsettled()->update([
                'settlement_status' => 'settled',
                'settlement_id' => $settlement->id,
                'settlement_date' => $settlement->settlement_date,
            ]);
            
            // 11. 创建本金调整记录（结算类型）
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
            
            // 12. 创建港币余额调整记录（结算类型）
            BalanceAdjustment::createHkdBalanceAdjustment(
                afterAmount: $newHkdBalance,
                adjustmentType: 'settlement',
                reason: sprintf(
                    '结算调整 - 结算号: %s, 利润: HK$ %s',
                    $sequenceNumber,
                    number_format($preview['profit'], 2)
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
