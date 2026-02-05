<?php

namespace App\Filament\Pages;

use App\Services\ReportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DailySettlementExport;
use App\Exports\MonthlySettlementExport;
use App\Exports\YearlySettlementExport;
use App\Exports\MonthlyDetailExport;
use Illuminate\Support\Facades\Gate;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = '财务报表';
    protected static ?string $navigationLabel = '日/月/年结报表';
    protected static ?string $title = '日/月/年结报表';
    protected static string $view = 'filament.pages.reports-page';

    public ?array $daily = [
        'date' => null,
    ];

    public ?array $monthly = [
        'year' => null,
        'month' => null,
        'other_expenses' => [],
    ];
    
    public $monthlyData = null;
    public $monthlyDetailData = null;
    public $dailyData = null;
    public $yearlyData = null;
    public $activeTab = 'monthly'; // 'daily', 'monthly', 'yearly', 'monthly-detail'

    public ?array $yearly = [
        'year' => null,
        'other_expenses' => [],
    ];

    public static function canAccess(): bool
    {
        return Gate::allows('export_transactions');
    }

    protected function getForms(): array
    {
        return [
            'dailyForm' => $this->makeDailyForm(),
            'monthlyForm' => $this->makeMonthlyForm(),
            'yearlyForm' => $this->makeYearlyForm(),
        ];
    }

    public function makeDailyForm(): Form
    {
        return Forms\Form::make($this)
            ->schema([
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('exportDaily')
                        ->label('导出Excel')
                        ->color('primary')
                        ->action('exportDaily')
                        ->size('sm'),
                ])->alignment('end')
            ]);
    }

    public function makeMonthlyForm(): Form
    {
        return Forms\Form::make($this)
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('monthly.year')
                            ->numeric()
                            ->label('年份')
                            ->default(now()->year)
                            ->required(),
                        Forms\Components\TextInput::make('monthly.month')
                            ->numeric()
                            ->label('月份')
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(now()->month)
                            ->required(),
                    ]),
                Forms\Components\Group::make([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generateMonthly')
                            ->label('查询')
                            ->action('generateMonthly'),
                        Forms\Components\Actions\Action::make('exportMonthly')
                            ->label('导出Excel')
                            ->color('primary')
                            ->action('exportMonthly')
                            ->visible(fn () => $this->monthlyData !== null),
                    ])
                ])
            ]);
    }

    public function makeYearlyForm(): Form
    {
        return Forms\Form::make($this)
            ->schema([
                Forms\Components\TextInput::make('yearly.year')->numeric()->label('年份')->required(),
                Forms\Components\KeyValue::make('yearly.other_expenses')
                    ->label('其他支出（名称=>HKD金额）')
                    ->keyLabel('名称')
                    ->valueLabel('港币金额'),
                Forms\Components\Group::make([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generateYearly')->label('生成')->action('generateYearly'),
                        Forms\Components\Actions\Action::make('exportYearly')->label('导出Excel')->color('primary')->action('exportYearly'),
                    ])
                ])
            ]);
    }

    public function generateDaily(ReportService $service): void
    {
        $date = data_get($this->daily, 'date');
        $data = $service->generateDailySettlement($date);
        Notification::make()->title('日结生成完成')->success()->send();
        session()->flash('reports.daily', $data);
    }

    public function exportDaily(ReportService $service)
    {
        $date = data_get($this->daily, 'date');
        // 使用 generateDailySettlement 方法获取渠道汇总数据
        $data = $service->generateDailySettlement($date);
        $filename = '日报表_' . $date . '.xlsx';
        
        return Excel::download(new DailySettlementExport($data), $filename);
    }

    public function persistDaily(ReportService $service): void
    {
        $date = data_get($this->daily, 'date');
        $data = $service->generateDailySettlement($date);
        $service->persistDailyCarryForward($date, $data);
        Notification::make()->title('结余记录已保存')->success()->send();
    }

    /**
     * 查询日报表数据
     */
    public function loadDailyReport(ReportService $service): void
    {
        $date = data_get($this->daily, 'date');
        
        if (!$date) {
            Notification::make()->title('请选择日期')->warning()->send();
            return;
        }

        $this->dailyData = $service->getDailyTransactionData($date);
    }

    /**
     * 加载月度明细数据
     */
    public function loadMonthlyDetailData(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail($year, $month);
    }

    public function mount(): void
    {
        // 初始化日报表日期
        $this->daily['date'] = now()->toDateString();
        
        // 初始化时加载当前月份数据
        $this->monthly['year'] = now()->year;
        $this->monthly['month'] = now()->month;
        
        // 初始化年度报表年份
        $this->yearly['year'] = now()->year;
        
        // 自动加载当月数据和当日数据
        $service = app(ReportService::class);
        $this->monthlyData = $service->getMonthlySettlementData(
            $this->monthly['year'],
            $this->monthly['month']
        );
        $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail(
            $this->monthly['year'],
            $this->monthly['month']
        );
        $this->dailyData = $service->getDailyTransactionData($this->daily['date']);
        $this->yearlyData = $service->getYearlyReportData($this->yearly['year']);
    }

    /**
     * 上一天
     */
    public function previousDay(ReportService $service): void
    {
        $currentDate = \Carbon\Carbon::parse($this->daily['date']);
        $previousDate = $currentDate->subDay();
        
        $this->daily['date'] = $previousDate->toDateString();
        $this->dailyData = $service->getDailyTransactionData($this->daily['date']);
    }

    /**
     * 下一天
     */
    public function nextDay(ReportService $service): void
    {
        $currentDate = \Carbon\Carbon::parse($this->daily['date']);
        $nextDate = $currentDate->addDay();
        
        $this->daily['date'] = $nextDate->toDateString();
        $this->dailyData = $service->getDailyTransactionData($this->daily['date']);
    }

    /**
     * 回到今天
     */
    public function goToday(ReportService $service): void
    {
        $this->daily['date'] = now()->toDateString();
        $this->dailyData = $service->getDailyTransactionData($this->daily['date']);
    }

    public function previousMonth(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        // 上一个月
        $month--;
        if ($month < 1) {
            $month = 12;
            $year--;
        }
        
        $this->monthly['year'] = $year;
        $this->monthly['month'] = $month;
        
        // 加载数据 - 同步更新结余报表和明细报表数据
        $this->monthlyData = $service->getMonthlySettlementData($year, $month);
        $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail($year, $month);
    }

    public function nextMonth(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        // 下一个月
        $month++;
        if ($month > 12) {
            $month = 1;
            $year++;
        }
        
        $this->monthly['year'] = $year;
        $this->monthly['month'] = $month;
        
        // 加载数据 - 同步更新结余报表和明细报表数据
        $this->monthlyData = $service->getMonthlySettlementData($year, $month);
        $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail($year, $month);
    }

    public function generateMonthly(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        // 查询该月的结余数据和明细数据
        $this->monthlyData = $service->getMonthlySettlementData($year, $month);
        $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail($year, $month);
        
        Notification::make()->title('月度报表加载完成')->success()->send();
    }

    public function exportMonthly(ReportService $service)
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        $data = $this->monthlyData ?? $service->getMonthlySettlementData($year, $month);
        $filename = '月度报表_' . $year . '年' . $month . '月.xlsx';
        
        $rows = [];
        foreach ($data['daily_data'] as $row) {
            // 只导出已结算的数据
            if ($row['has_settlement']) {
                $rows[] = [
                    $row['date'],                    // 日期
                    $row['previous_capital'],        // 本金
                    $row['profit'],                  // 利润
                    $row['income'] ?? 0,             // 收入
                    $row['expenses'],                // 支出
                    $row['new_capital'],             // 结余本金
                    $row['rmb_balance'],             // 人民币结余
                    $row['hkd_balance'],             // 港币结余
                    $row['notes'] ?? '',             // 备注
                ];
            }
        }
        
        return Excel::download(new MonthlySettlementExport($rows), $filename);
    }

    /**
     * 导出月度收支明细报表
     */
    public function exportMonthlyDetail(ReportService $service)
    {
        try {
            $year = (int) data_get($this->monthly, 'year');
            $month = (int) data_get($this->monthly, 'month');
            
            // 验证年份和月份
            if (!$year || !$month) {
                Notification::make()
                    ->title('导出失败')
                    ->body('请先选择有效的年份和月份')
                    ->warning()
                    ->send();
                return;
            }
            
            // 获取月度明细数据（如果尚未加载）
            $data = $this->monthlyDetailData ?? $service->getMonthlyIncomeExpenseDetail($year, $month);
            
            // 验证数据是否存在
            if (empty($data) || (empty($data['income_data']) && empty($data['expense_data']))) {
                Notification::make()
                    ->title('导出失败')
                    ->body('选定月份没有可导出的数据')
                    ->warning()
                    ->send();
                return;
            }
            
            // 格式化文件名：月度收支明细表_YYYY年MM月.xlsx
            $filename = sprintf('月度收支明细表_%d年%02d月.xlsx', $year, $month);
            
            // 生成并下载Excel文件
            return Excel::download(new MonthlyDetailExport($data, $year, $month), $filename);
            
        } catch (\Exception $e) {
            // 记录错误日志
            \Log::error('Monthly detail export failed', [
                'year' => $year ?? null,
                'month' => $month ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            // 显示用户友好的错误消息
            Notification::make()
                ->title('导出失败')
                ->body('生成Excel文件时发生错误，请稍后重试或联系管理员')
                ->danger()
                ->send();
            
            return null;
        }
    }

    public function generateYearly(ReportService $service): void
    {
        $year = (int) data_get($this->yearly, 'year');
        $other = $this->normalizeOther(data_get($this->yearly, 'other_expenses', []));
        $data = $service->generateYearlySettlement($year, $other);
        Notification::make()->title('年结生成完成')->success()->send();
        session()->flash('reports.yearly', $data);
    }

    public function exportYearly(ReportService $service)
    {
        $year = (int) data_get($this->yearly, 'year');
        $other = $this->normalizeOther(data_get($this->yearly, 'other_expenses', []));
        $data = $service->generateYearlySettlement($year, $other);
        $filename = '年度报表_' . $year . '年.xlsx';
        
        $rows = [];
        foreach ($data['yearly_data'] as $row) {
            $rows[] = [
                $row['month'],
                $row['principal'],
                $row['total_income'],
                $row['total_expense'],
                $data['other_expenses'],
                $row['profit']
            ];
        }
        
        return Excel::download(new YearlySettlementExport($rows), $filename);
    }

    /**
     * 更新支出项目备注（内联编辑功能）
     */
    public function updateExpenseRemark(int $expenseId, string $remark): void
    {
        try {
            $service = app(ReportService::class);
            $result = $service->updateSettlementExpenseRemark($expenseId, $remark);
            
            if ($result['success']) {
                // 更新成功，重新加载明细数据以反映更改
                $year = (int) data_get($this->monthly, 'year');
                $month = (int) data_get($this->monthly, 'month');
                
                if ($year && $month) {
                    $this->monthlyDetailData = $service->getMonthlyIncomeExpenseDetail($year, $month);
                }
                
                Notification::make()
                    ->title('备注更新成功')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                // 更新失败，显示具体错误信息
                $errorMessage = $result['message'] ?? '备注更新失败';
                
                Notification::make()
                    ->title('备注更新失败')
                    ->body($errorMessage)
                    ->danger()
                    ->send();
                
                // 记录详细错误信息用于调试
                if (isset($result['errors'])) {
                    \Log::warning('Expense remark update failed with validation errors', [
                        'expense_id' => $expenseId,
                        'remark' => $remark,
                        'errors' => $result['errors'],
                        'user_id' => auth()->id()
                    ]);
                }
            }
        } catch (\Exception $e) {
            // 捕获意外异常
            \Log::error('Unexpected error in updateExpenseRemark', [
                'expense_id' => $expenseId,
                'remark' => $remark,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            Notification::make()
                ->title('系统错误')
                ->body('更新备注时发生系统错误，请稍后重试或联系管理员')
                ->danger()
                ->send();
        }
    }

    private function normalizeOther(array $kv): array
    {
        $out = [];
        foreach ($kv as $name => $amount) {
            $out[] = ['name' => $name, 'amount_hkd' => (float) $amount];
        }
        return $out;
    }

    /**
     * 上一年
     */
    public function previousYear(ReportService $service): void
    {
        $year = (int) data_get($this->yearly, 'year');
        $year--;
        
        $this->yearly['year'] = $year;
        $this->yearlyData = $service->getYearlyReportData($year);
    }

    /**
     * 下一年
     */
    public function nextYear(ReportService $service): void
    {
        $year = (int) data_get($this->yearly, 'year');
        $year++;
        
        $this->yearly['year'] = $year;
        $this->yearlyData = $service->getYearlyReportData($year);
    }

    /**
     * 更新季度分红
     */
    public function updateDividend(int $year, int $month, float $amount): void
    {
        try {
            $service = app(ReportService::class);
            $result = $service->updateQuarterlyDividend($year, $month, $amount);
            
            if ($result['success']) {
                // 更新成功，重新加载年度数据
                $this->yearlyData = $service->getYearlyReportData($year);
                
                Notification::make()
                    ->title('季度分红更新成功')
                    ->body($result['message'])
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('季度分红更新失败')
                    ->body($result['message'] ?? '更新失败')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            \Log::error('Unexpected error in updateDividend', [
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            Notification::make()
                ->title('系统错误')
                ->body('更新季度分红时发生系统错误')
                ->danger()
                ->send();
        }
    }
}


