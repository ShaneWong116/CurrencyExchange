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
                Forms\Components\DatePicker::make('daily.date')
                    ->label('日期')
                    ->required(),
                Forms\Components\Group::make([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generateDaily')
                            ->label('生成')
                            ->action('generateDaily'),
                        Forms\Components\Actions\Action::make('exportDaily')
                            ->label('导出Excel')
                            ->color('primary')
                            ->action('exportDaily'),
                        Forms\Components\Actions\Action::make('persistDaily')
                            ->label('保存为结余')
                            ->color('success')
                            ->action('persistDaily'),
                    ])->alignment('start')
                ])
            ]);
    }

    public function makeMonthlyForm(): Form
    {
        return Forms\Form::make($this)
            ->schema([
                Forms\Components\TextInput::make('monthly.year')->numeric()->label('年份')->required(),
                Forms\Components\TextInput::make('monthly.month')->numeric()->label('月份')->required(),
                Forms\Components\KeyValue::make('monthly.other_expenses')
                    ->label('其他支出（名称=>HKD金额）')
                    ->keyLabel('名称')
                    ->valueLabel('港币金额'),
                Forms\Components\Group::make([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('generateMonthly')->label('生成')->action('generateMonthly'),
                        Forms\Components\Actions\Action::make('exportMonthly')->label('导出Excel')->color('primary')->action('exportMonthly'),
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

    public function exportDaily(ReportService $service): void
    {
        $date = data_get($this->daily, 'date');
        $data = $service->generateDailySettlement($date);
        $filename = 'daily_settlement_' . str_replace('-', '', $date) . '.xlsx';
        $path = 'exports/' . $filename;
        Excel::store(new DailySettlementExport($data), $path, 'public');
        Notification::make()->title('导出成功')->body('下载：/storage/' . $path)->success()->send();
    }

    public function persistDaily(ReportService $service): void
    {
        $date = data_get($this->daily, 'date');
        $data = $service->generateDailySettlement($date);
        $service->persistDailyCarryForward($date, $data);
        Notification::make()->title('结余记录已保存')->success()->send();
    }

    public function generateMonthly(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        $other = $this->normalizeOther(data_get($this->monthly, 'other_expenses', []));
        $data = $service->generateMonthlySettlement($year, $month, $other);
        Notification::make()->title('月结生成完成')->success()->send();
        session()->flash('reports.monthly', $data);
    }

    public function exportMonthly(ReportService $service): void
    {
        $year = (int) data_get($this->monthly, 'year');
        $month = (int) data_get($this->monthly, 'month');
        $other = $this->normalizeOther(data_get($this->monthly, 'other_expenses', []));
        $data = $service->generateMonthlySettlement($year, $month, $other);
        $filename = 'monthly_settlement_' . $year . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.xlsx';
        $path = 'exports/' . $filename;
        $rows = [];
        foreach ($data['monthly_data'] as $row) {
            $rows[] = [$row['date'], $row['principal'], $row['total_income'], $row['total_expense'], $data['other_expenses'], $row['profit']];
        }
        Excel::store(new MonthlySettlementExport($rows), $path, 'public');
        Notification::make()->title('导出成功')->body('下载：/storage/' . $path)->success()->send();
    }

    public function generateYearly(ReportService $service): void
    {
        $year = (int) data_get($this->yearly, 'year');
        $other = $this->normalizeOther(data_get($this->yearly, 'other_expenses', []));
        $data = $service->generateYearlySettlement($year, $other);
        Notification::make()->title('年结生成完成')->success()->send();
        session()->flash('reports.yearly', $data);
    }

    public function exportYearly(ReportService $service): void
    {
        $year = (int) data_get($this->yearly, 'year');
        $other = $this->normalizeOther(data_get($this->yearly, 'other_expenses', []));
        $data = $service->generateYearlySettlement($year, $other);
        $filename = 'yearly_settlement_' . $year . '.xlsx';
        $path = 'exports/' . $filename;
        $rows = [];
        foreach ($data['yearly_data'] as $row) {
            $rows[] = [$row['month'], $row['principal'], $row['total_income'], $row['total_expense'], $data['other_expenses'], $row['profit']];
        }
        Excel::store(new YearlySettlementExport($rows), $path, 'public');
        Notification::make()->title('导出成功')->body('下载：/storage/' . $path)->success()->send();
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


