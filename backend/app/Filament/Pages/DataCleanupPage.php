<?php

namespace App\Filament\Pages;

use App\Models\Setting;
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
                ->revealable()
                ->required()
                ->helperText('请输入数据清理验证密码'),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('cleanup')
                    ->label('清空数据')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('是否清空所选数据？')
                    ->modalSubheading('清空后无法恢复，请谨慎操作。')
                    ->action('performCleanup'),
            ]),
            
            Forms\Components\Placeholder::make('password_hint')
                ->label('密码提示')
                ->content(fn () => $this->getPasswordHint())
                ->columnSpanFull(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->getFormSchema());
    }

    public function performCleanup(CleanupService $service): void
    {
        $payload = $this->formData;
        
        // 验证密码
        if (!$this->verifyPassword($payload['verification_password'] ?? '')) {
            Notification::make()
                ->title('密码验证失败')
                ->danger()
                ->body('二次验证密码错误，请输入正确的密码')
                ->send();
            return;
        }
        
        $deleted = $service->cleanup($payload, auth()->user()->name ?? 'system');

        Notification::make()
            ->title('清空成功')
            ->body('删除结果：' . json_encode($deleted, JSON_UNESCAPED_UNICODE))
            ->success()
            ->send();
            
        // 清空密码字段
        $this->formData['verification_password'] = '';
    }
    
    /**
     * 验证密码
     */
    private function verifyPassword(string $password): bool
    {
        if (empty($password)) {
            return false;
        }
        
        $setting = Setting::where('key_name', 'cleanup_password')->first();
        
        if (!$setting) {
            // 如果没有设置，使用默认密码
            return $password === '123456';
        }
        
        return password_verify($password, $setting->key_value);
    }
    
    /**
     * 获取密码提示
     */
    private function getPasswordHint(): string
    {
        $setting = Setting::where('key_name', 'cleanup_password')->first();
        
        if (!$setting) {
            return '⚠️ 未设置清理验证密码，当前使用默认密码: 123456。请在【系统设置】中修改密码以确保安全！';
        }
        
        return '💡 请输入在系统设置中配置的数据清理验证密码。如忘记密码，请联系系统管理员。';
    }
}


