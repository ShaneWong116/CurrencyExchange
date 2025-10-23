<?php

namespace App\Filament\Pages;

use App\Services\CleanupService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class DataCleanupPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trash';
    protected static ?string $navigationGroup = '系统维护';
    protected static ?string $navigationLabel = '数据清理';
    protected static ?string $title = '数据清理';
    protected static string $view = 'filament.pages.data-cleanup-page';

    public ?array $formData = [
        'time_range' => 'day',
        'start_date' => null,
        'end_date' => null,
        'content_types' => [],
        'verification_password' => '',
    ];

    public static function canAccess(): bool
    {
        return Gate::allows('manage_system');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('formData.time_range')
                ->label('时间范围')
                ->options([
                    'day' => '今天',
                    'month' => '本月',
                    'year' => '今年',
                    'all' => '全部',
                    'custom' => '自定义',
                ])
                ->required(),

            Forms\Components\DatePicker::make('formData.start_date')
                ->label('开始日期')
                ->visible(fn (Forms\Get $get) => $get('formData.time_range') === 'custom'),

            Forms\Components\DatePicker::make('formData.end_date')
                ->label('结束日期')
                ->visible(fn (Forms\Get $get) => $get('formData.time_range') === 'custom'),

            Forms\Components\CheckboxList::make('formData.content_types')
                ->label('清理内容')
                ->options([
                    'channels' => '渠道',
                    'balances' => '余额',
                    'accounts' => '账号',
                    'bills' => '账单',
                    'locations' => '地点',
                ])
                ->columns(2)
                ->required(),

            Forms\Components\TextInput::make('formData.verification_password')
                ->label('二次验证密码')
                ->password()
                ->required(),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('cleanup')
                    ->label('清空数据')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('是否清空所选数据？')
                    ->modalSubheading('清空后无法恢复，请谨慎操作。')
                    ->action('performCleanup'),
            ]),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->getFormSchema());
    }

    public function performCleanup(CleanupService $service): void
    {
        $payload = $this->formData;
        $deleted = $service->cleanup($payload, auth()->user()->name ?? 'system');

        Notification::make()
            ->title('清空成功')
            ->body('删除结果：' . json_encode($deleted, JSON_UNESCAPED_UNICODE))
            ->success()
            ->send();
    }
}


