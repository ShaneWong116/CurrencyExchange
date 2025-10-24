<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\SettlementExpense;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\Setting;
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
     * 获取结余预览数据
     */
    public function getPreview()
    {
        // 1. 获取当前本金和港币结余
        $currentCapital = (float) Setting::get('capital', 0);
        $currentHkdBalance = (float) Setting::get('hkd_balance', 0);
        // 2. 计算当前各渠道人民币余额汇总
        $channels = Channel::all();
        $rmbBalanceTotal = 0;
        foreach ($channels as $channel) {
            $rmbBalanceTotal += $channel->getRmbBalance();
        }

        // 3. 获取未结余的入账交易数据
        $unsettledIncomeTransactions = Transaction::unsettled()
            ->where('type', 'income')
            ->get();
        
        $unsettledIncomeRmb = $unsettledIncomeTransactions->sum('rmb_amount');
        $unsettledIncomeHkd = $unsettledIncomeTransactions->sum('hkd_amount');

        // 4. 计算当前结余汇率
        // 人民币总量 = 当前各渠道人民币余额汇总 + 未结余入账人民币金额之和
        $rmbTotal = $rmbBalanceTotal + $unsettledIncomeRmb;
        
        // 港币总量 = 当前港币结余 + 未结余入账港币金额之和
        $hkdTotal = $currentHkdBalance + $unsettledIncomeHkd;
        
        // 当前结余汇率 = 人民币总量 ÷ 港币总量（保留3位小数）
        $settlementRate = $hkdTotal > 0 ? round($rmbTotal / $hkdTotal, 3) : 0;

        // 5. 获取未结余的出账交易数据
        $unsettledOutcomeTransactions = Transaction::unsettled()
            ->where('type', 'outcome')
            ->get();
        
        // 出账港币总额
        $outcomeHkdTotal = $unsettledOutcomeTransactions->sum('hkd_amount');
        
        // 出账人民币总额
        $outcomeRmbTotal = $unsettledOutcomeTransactions->sum('rmb_amount');
        
        // 出账港币成本 = 出账人民币总额 ÷ 当前结余汇率
        $outcomeHkdCost = $settlementRate > 0 ? round($outcomeRmbTotal / $settlementRate, 3) : 0;
        
        // 利润 = 出账港币总额 - 出账港币成本
        $profit = round($outcomeHkdTotal - $outcomeHkdCost, 3);

        // 计算其他支出为0时的预期结果
        $expectedNewCapital = $currentCapital + $profit;
        $expectedNewHkdBalance = $currentHkdBalance + $profit;

        return [
            // 当前状态
            'current_capital' => $currentCapital,
            'current_hkd_balance' => $currentHkdBalance,
            'rmb_balance_total' => round($rmbBalanceTotal, 2),
            
            // 计算结果
            'settlement_rate' => $settlementRate,
            'profit' => $profit,
            
            // 预期结余后状态（不含其他支出）
            'expected_new_capital' => round($expectedNewCapital, 2),
            'expected_new_hkd_balance' => round($expectedNewHkdBalance, 2),
            
            // 未结余交易统计
            'unsettled_income_count' => $unsettledIncomeTransactions->count(),
            'unsettled_outcome_count' => $unsettledOutcomeTransactions->count(),
            'unsettled_income_rmb' => round($unsettledIncomeRmb, 2),
            'unsettled_income_hkd' => round($unsettledIncomeHkd, 2),
            'unsettled_outcome_rmb' => round($outcomeRmbTotal, 2),
            'unsettled_outcome_hkd' => round($outcomeHkdTotal, 2),
            'outcome_hkd_cost' => $outcomeHkdCost,
            
            // 是否可以执行结余
            'can_settle' => ($unsettledIncomeTransactions->count() + $unsettledOutcomeTransactions->count()) > 0,
        ];
    }

    /**
     * 执行结余操作
     * 
     * @param array $expenses 其他支出明细 [['item_name' => '薪金', 'amount' => 100], ...]
     * @param string|null $notes 备注
     * @return Settlement
     */
    public function execute(array $expenses = [], ?string $notes = null)
    {
        return DB::transaction(function () use ($expenses, $notes) {
            // 1. 获取预览数据
            $preview = $this->getPreview();
            
            // 2. 业务校验
            if (!$preview['can_settle']) {
                throw new Exception('当前没有未结余的交易，无法执行结余操作');
            }
            
            if ($preview['settlement_rate'] <= 0) {
                throw new Exception('结余汇率计算异常，请检查港币结余是否正确');
            }
            
            // 3. 计算其他支出总额
            $otherExpensesTotal = 0;
            foreach ($expenses as $expense) {
                $otherExpensesTotal += $expense['amount'] ?? 0;
            }
            
            // 4. 计算结余后的数据
            $newCapital = $preview['current_capital'] + $preview['profit'] - $otherExpensesTotal;
            $newHkdBalance = $preview['current_hkd_balance'] + $preview['profit'];
            
            // 5. 获取下一个序号
            $sequenceNumber = Settlement::getNextSequenceNumber();
            
            // 6. 创建结余记录
            $settlement = Settlement::create([
                'previous_capital' => $preview['current_capital'],
                'previous_hkd_balance' => $preview['current_hkd_balance'],
                'profit' => $preview['profit'],
                'other_expenses_total' => $otherExpensesTotal,
                'new_capital' => $newCapital,
                'new_hkd_balance' => $newHkdBalance,
                'settlement_rate' => $preview['settlement_rate'],
                'rmb_balance_total' => $preview['rmb_balance_total'],
                'sequence_number' => $sequenceNumber,
                'notes' => $notes,
            ]);
            
            // 7. 保存其他支出明细
            if (!empty($expenses)) {
                foreach ($expenses as $expense) {
                    SettlementExpense::create([
                        'settlement_id' => $settlement->id,
                        'item_name' => $expense['item_name'],
                        'amount' => $expense['amount'],
                    ]);
                }
            }
            
            // 8. 更新所有未结余的交易状态
            Transaction::unsettled()->update([
                'settlement_status' => 'settled',
                'settlement_id' => $settlement->id,
            ]);
            
            // 9. 更新系统设置中的本金和港币结余
            Setting::set('capital', round($newCapital, 2), '系统本金(HKD)', 'number');
            Setting::set('hkd_balance', round($newHkdBalance, 2), '港币结余(HKD)', 'number');
            
            return $settlement;
        });
    }

    /**
     * 获取结余详情
     */
    public function getDetail($settlementId)
    {
        $settlement = Settlement::with('expenses', 'transactions')->findOrFail($settlementId);
        
        return [
            'settlement' => $settlement,
            'expenses' => $settlement->expenses,
            'transactions_count' => $settlement->transactions->count(),
            'income_transactions_count' => $settlement->transactions->where('type', 'income')->count(),
            'outcome_transactions_count' => $settlement->transactions->where('type', 'outcome')->count(),
        ];
    }

    /**
     * 获取结余历史列表
     */
    public function getHistory($page = 1, $perPage = 20)
    {
        return Settlement::with('expenses')
            ->orderBy('sequence_number', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
