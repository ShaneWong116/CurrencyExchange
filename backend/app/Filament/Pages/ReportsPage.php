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
    public $dailyData = null;
    public $activeTab = 'monthly'; // 'daily', 'monthly', 'yearly'

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
        $data = $service->getDailyTransactionData($date);
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

    public function mount(): void
    {
        // 初始化日报表日期
        $this->daily['date'] = now()->toDateString();
        
        // 初始化时加载当前月份数据
        $this->monthly['year'] = now()->year;
        $this->monthly['month'] = now()->month;
        
        // 自动加载当月数据和当日数据
        $service = app(ReportService::class);
        $this->monthlyData = $service->getMonthlySettlementData(
            $this->monthly['year'],
            $this->monthly['month']
        );
        $this->dailyData = $service->getDailyTransactionData($this->daily['date']);
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
        
        // 加载数据
        $this->monthlyData = $service->getMonthlySettlementData($year, $month);
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
        
        // 加载数据
        $this->monthlyData = $service->getMonthlySettlementData($year, $month);
    }

    public function generateMonthly(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        
        // 查询该月的结余数据
        $data = $service->getMonthlySettlementData($year, $month);
        
        $this->monthlyData = $data;
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
            $rows[] = [
                $row['date'],
                $row['previous_capital'],
                $row['profit'],
                $row['expenses'],
                $row['new_capital'],
                $row['rmb_balance'],
                $row['hkd_balance'],
                $row['notes'] ?? '',
            ];
        }
        
        return Excel::download(new MonthlySettlementExport($rows), $filename);
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

    private function normalizeOther(array $kv): array
    {
        $out = [];
        foreach ($kv as $name => $amount) {
            $out[] = ['name' => $name, 'amount_hkd' => (float) $amount];
        }
        return $out;
    }
}


