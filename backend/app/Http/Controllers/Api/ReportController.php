<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Exports\DailySettlementExport;
use App\Exports\MonthlySettlementExport;
use App\Exports\YearlySettlementExport;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 日结报告：生成或导出
     */
    public function dailySettlement(Request $request)
    {
        $this->authorize('export_transactions');
        $request->validate([
            'date' => 'sometimes|date',
            'action' => 'nullable|in:generate,export,persist',
        ]);

        $date = $request->input('date', now()->toDateString());
        $action = $request->input('action', 'generate');

        $data = $this->reportService->generateDailySettlement($date);

        if ($action === 'persist') {
            $this->reportService->persistDailyCarryForward($date, $data);
        }

        if ($action === 'export') {
            $filename = 'daily_settlement_' . str_replace('-', '', $date) . '.xlsx';
            return Excel::download(new DailySettlementExport($data), $filename);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function toCsv(array $data): string
    {
        $lines = [];
        $lines[] = '渠道,昨日结余(人民币),今日入账(人民币),今日出账(人民币),当前余额(人民币),今日入账(港币),今日出账(港币),利润(港币)';
        foreach ($data['channels'] as $row) {
            $lines[] = implode(',', [
                $row['channel_name'],
                $row['yesterday_balance'],
                $row['today_income_cny'],
                $row['today_expense_cny'],
                $row['current_balance'],
                $row['today_income_hkd'],
                $row['today_expense_hkd'],
                $row['profit'],
            ]);
        }
        $lines[] = implode(',', ['合计', '', '', '', $data['total_balance'], '', '', $data['total_profit']]);
        return implode("\n", $lines);
    }

    /**
     * 月结报告：生成或导出
     */
    public function monthlySettlement(Request $request)
    {
        $this->authorize('export_transactions');
        $request->validate([
            'year' => 'sometimes|integer|min:1970',
            'month' => 'sometimes|integer|min:1|max:12',
            'other_expenses' => 'sometimes|array',
        ]);

        $now = now();
        $year = (int) ($request->input('year') ?? $now->year);
        $month = (int) ($request->input('month') ?? $now->month);
        if ($month < 1 || $month > 12) { $month = (int) $now->month; }
        $otherExpenses = $request->input('other_expenses', []);
        $action = $request->input('action', 'generate');

        $data = $this->reportService->generateMonthlySettlement($year, $month, $otherExpenses);

        if ($action === 'export') {
            $filename = "monthly_settlement_{$year}" . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '.xlsx';
            // 转为行数组
            $rows = [];
            foreach ($data['monthly_data'] as $row) {
                $rows[] = [
                    $row['date'], $row['principal'], $row['total_income'], $row['total_expense'], $data['other_expenses'], $row['profit']
                ];
            }
            return Excel::download(new MonthlySettlementExport($rows), $filename);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 年结报告：生成或导出
     */
    public function yearlySettlement(Request $request)
    {
        $this->authorize('export_transactions');
        $request->validate([
            'year' => 'sometimes|integer|min:1970',
            'other_expenses' => 'sometimes|array',
        ]);

        $year = (int) ($request->input('year') ?? now()->year);
        $otherExpenses = $request->input('other_expenses', []);
        $action = $request->input('action', 'generate');

        $data = $this->reportService->generateYearlySettlement($year, $otherExpenses);

        if ($action === 'export') {
            $filename = "yearly_settlement_{$year}.xlsx";
            $rows = [];
            foreach ($data['yearly_data'] as $row) {
                $rows[] = [
                    $row['month'], $row['principal'], $row['total_income'], $row['total_expense'], $data['other_expenses'], $row['profit']
                ];
            }
            return Excel::download(new YearlySettlementExport($rows), $filename);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}


