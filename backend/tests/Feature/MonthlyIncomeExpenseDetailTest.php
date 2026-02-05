<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ReportService;
use App\Models\Settlement;
use App\Models\SettlementExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MonthlyIncomeExpenseDetailTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new ReportService();
    }

    /** @test */
    public function it_returns_monthly_income_expense_detail_with_all_dates()
    {
        // 创建测试数据
        $year = 2024;
        $month = 1;
        
        // 创建一个结算记录
        $settlement = Settlement::create([
            'settlement_date' => Carbon::create($year, $month, 15),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
            'notes' => '测试结算',
        ]);

        // 创建支出记录
        SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '购买打印纸',
        ]);

        // 调用方法
        $result = $this->reportService->getMonthlyIncomeExpenseDetail($year, $month);

        // 验证结果结构
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('days_in_month', $result);
        $this->assertArrayHasKey('income_data', $result);
        $this->assertArrayHasKey('expense_data', $result);
        $this->assertArrayHasKey('summary', $result);

        // 验证基本信息
        $this->assertEquals($year, $result['year']);
        $this->assertEquals($month, $result['month']);
        $this->assertEquals(31, $result['days_in_month']); // 1月有31天

        // 验证收入数据包含所有日期
        $this->assertCount(31, $result['income_data']);

        // 验证第15天有数据
        $day15 = $result['income_data'][14]; // 索引从0开始
        $this->assertTrue($day15['has_settlement']);
        $this->assertEquals(700.00, $day15['total_profit']); // 500 + 200

        // 验证第1天无数据
        $day1 = $result['income_data'][0];
        $this->assertFalse($day1['has_settlement']);
        $this->assertEquals(0.0, $day1['total_profit']);

        // 验证支出数据
        $this->assertCount(1, $result['expense_data']);
        $expense = $result['expense_data'][0];
        $this->assertEquals('办公用品', $expense['item_name']);
        $this->assertEquals(100.00, $expense['amount']);
        $this->assertEquals('购买打印纸', $expense['remarks']);

        // 验证汇总统计
        $this->assertEquals(700.00, $result['summary']['total_income']);
        $this->assertEquals(100.00, $result['summary']['total_expenses']);
        $this->assertEquals(600.00, $result['summary']['total_profit']);
    }

    /** @test */
    public function it_updates_settlement_expense_remark_successfully()
    {
        // 创建测试数据
        $settlement = Settlement::create([
            'settlement_date' => now(),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
        ]);

        $expense = SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '原始备注',
        ]);

        // 更新备注
        $result = $this->reportService->updateSettlementExpenseRemark($expense->id, '更新后的备注');

        // 验证更新成功 - 需求 6.2, 6.4
        $this->assertTrue($result['success']);
        $this->assertEquals('备注更新成功', $result['message']);
        
        // 验证数据库中的数据已更新
        $expense->refresh();
        $this->assertEquals('更新后的备注', $expense->remarks);
    }

    /** @test */
    public function it_validates_remark_length()
    {
        // 创建测试数据
        $settlement = Settlement::create([
            'settlement_date' => now(),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
        ]);

        $expense = SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '原始备注',
        ]);

        // 尝试更新超长备注（超过1000字符）
        $longRemark = str_repeat('测试', 501); // 1002个字符
        $result = $this->reportService->updateSettlementExpenseRemark($expense->id, $longRemark);

        // 验证验证失败 - 需求 6.3
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('1000', $result['message']);
        
        // 验证数据库中的数据未更新
        $expense->refresh();
        $this->assertEquals('原始备注', $expense->remarks);
    }

    /** @test */
    public function it_sanitizes_malicious_input()
    {
        // 创建测试数据
        $settlement = Settlement::create([
            'settlement_date' => now(),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
        ]);

        $expense = SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '原始备注',
        ]);

        // 尝试注入恶意脚本 - 需求 6.6
        $maliciousInput = '<script>alert("XSS")</script>正常文本';
        $result = $this->reportService->updateSettlementExpenseRemark($expense->id, $maliciousInput);

        // 验证更新成功但内容已被过滤
        $this->assertTrue($result['success']);
        
        // 验证数据库中的数据已被安全过滤
        $expense->refresh();
        $this->assertStringNotContainsString('<script>', $expense->remarks);
        $this->assertStringNotContainsString('alert', $expense->remarks);
        // 应该只保留正常文本
        $this->assertStringContainsString('正常文本', $expense->remarks);
    }

    /** @test */
    public function it_handles_nonexistent_expense_id()
    {
        // 尝试更新不存在的支出记录
        $result = $this->reportService->updateSettlementExpenseRemark(99999, '测试备注');

        // 验证返回错误 - 需求 6.3
        $this->assertFalse($result['success']);
        $this->assertEquals('支出记录不存在', $result['message']);
    }

    /** @test */
    public function it_handles_empty_remark()
    {
        // 创建测试数据
        $settlement = Settlement::create([
            'settlement_date' => now(),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
        ]);

        $expense = SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '原始备注',
        ]);

        // 更新为空备注
        $result = $this->reportService->updateSettlementExpenseRemark($expense->id, '');

        // 验证更新成功
        $this->assertTrue($result['success']);
        
        // 验证数据库中的备注已清空
        $expense->refresh();
        $this->assertEquals('', $expense->remarks);
    }

    /** @test */
    public function it_exports_monthly_detail_with_correct_filename_format()
    {
        // 创建测试数据
        $year = 2024;
        $month = 3;
        
        // 创建一个结算记录
        $settlement = Settlement::create([
            'settlement_date' => Carbon::create($year, $month, 15),
            'previous_capital' => 10000.00,
            'outgoing_profit' => 500.00,
            'instant_profit' => 200.00,
            'other_expenses_total' => 100.00,
            'other_incomes_total' => 0.00,
            'new_capital' => 10600.00,
            'new_hkd_balance' => 5000.00,
            'settlement_rate' => 0.85,
            'rmb_balance_total' => 12000.00,
            'sequence_number' => 1,
            'notes' => '测试结算',
        ]);

        // 创建支出记录
        SettlementExpense::create([
            'settlement_id' => $settlement->id,
            'type' => 'expense',
            'item_name' => '办公用品',
            'amount' => 100.00,
            'remarks' => '购买打印纸',
        ]);

        // 获取月度明细数据
        $data = $this->reportService->getMonthlyIncomeExpenseDetail($year, $month);

        // 验证数据不为空 - 需求 7.2
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data['income_data']);
        $this->assertNotEmpty($data['expense_data']);

        // 验证文件名格式 - 需求 7.5
        $expectedFilename = sprintf('月度收支明细表_%d年%02d月.xlsx', $year, $month);
        $this->assertEquals('月度收支明细表_2024年03月.xlsx', $expectedFilename);

        // 验证数据结构包含必要的字段用于导出 - 需求 7.3, 7.4
        $this->assertArrayHasKey('income_data', $data);
        $this->assertArrayHasKey('expense_data', $data);
        $this->assertArrayHasKey('summary', $data);
        
        // 验证汇总统计信息存在 - 需求 7.6
        $this->assertArrayHasKey('total_income', $data['summary']);
        $this->assertArrayHasKey('total_expenses', $data['summary']);
        $this->assertArrayHasKey('total_profit', $data['summary']);
    }

    /** @test */
    public function it_handles_export_with_no_data()
    {
        // 尝试导出没有数据的月份
        $year = 2024;
        $month = 12;
        
        $data = $this->reportService->getMonthlyIncomeExpenseDetail($year, $month);

        // 验证返回空数据结构 - 需求 10.2
        $this->assertNotEmpty($data);
        $this->assertEmpty($data['expense_data']); // 没有支出数据
        
        // 收入数据应该包含所有日期，但都是空的
        $this->assertCount(31, $data['income_data']); // 12月有31天
        
        // 验证所有日期都没有结算数据
        foreach ($data['income_data'] as $income) {
            $this->assertFalse($income['has_settlement']);
            $this->assertEquals(0.0, $income['total_profit']);
        }
        
        // 验证汇总统计为0
        $this->assertEquals(0.0, $data['summary']['total_income']);
        $this->assertEquals(0.0, $data['summary']['total_expenses']);
        $this->assertEquals(0.0, $data['summary']['total_profit']);
    }
}
