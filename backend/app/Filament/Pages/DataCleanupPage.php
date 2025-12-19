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
        'content_types_accounts' => [],
        'content_types_data' => [],
        'content_types_base' => [],
        'verification_password' => '',
    ];
    


    public static function canAccess(): bool
    {
        return Gate::allows('manage_system');
    }

    protected function getFormSchema(): array
    {
        return [
            // 一键全选/取消全选按钮
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('selectAll')
                    ->label('一键全选')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->action('selectAllOptions'),
                Forms\Components\Actions\Action::make('clearAll')
                    ->label('取消全选')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->action('clearAllOptions'),
            ])->columnSpanFull(),

            // 账号类
            Forms\Components\Section::make('账号类')
                ->description('外勤人员账号相关数据')
                ->schema([
                    Forms\Components\CheckboxList::make('formData.content_types_accounts')
                        ->label('')
                        ->options([
                            'accounts' => '外勤账号',
                        ])
                        ->descriptions([
                            'accounts' => '⚠️ 删除全部外勤账号（会同时删除全部交易记录和草稿）',
                        ])
                        ->columns(1),
                ])
                ->collapsible(),

            // 数据类
            Forms\Components\Section::make('数据类')
                ->description('业务数据记录')
                ->schema([
                    Forms\Components\CheckboxList::make('formData.content_types_data')
                        ->label('')
                        ->options([
                            'bills' => '交易记录（账单）',
                            'drafts' => '交易草稿',
                            'settlements' => '结算记录',
                            'images' => '图片',
                            'statistics' => '统计数据',
                            'audit_logs' => '审计日志',
                            'notifications' => '通知',
                        ])
                        ->descriptions([
                            'bills' => '删除所有交易记录（包括已结算的），同时删除关联的图片',
                            'drafts' => '删除所有交易草稿，同时删除关联的图片',
                            'settlements' => '删除所有结算记录、结算支出明细及结算关联的余额调整',
                            'images' => '删除所有图片',
                            'statistics' => '清空当前统计数据和每日统计数据',
                            'audit_logs' => '删除所有审计日志记录',
                            'notifications' => '删除所有通知记录',
                        ])
                        ->columns(2),
                ])
                ->collapsible(),

            // 基础类
            Forms\Components\Section::make('基础类')
                ->description('系统基础配置数据')
                ->schema([
                    Forms\Components\CheckboxList::make('formData.content_types_base')
                        ->label('')
                        ->options([
                            'channels' => '渠道',
                            'locations' => '地点',
                            'balances' => '渠道余额',
                            'adjustments' => '余额/本金调整记录',
                            'carry_forward' => '余额结转',
                            'other_expenses' => '其他支出',
                        ])
                        ->descriptions([
                            'channels' => '⚠️ 删除全部渠道（会同时删除全部交易记录、草稿、渠道余额）',
                            'locations' => '删除全部地点（会清除关联数据的地点引用）',
                            'balances' => '删除全部渠道余额记录',
                            'adjustments' => '⚠️ 删除全部余额调整记录，并将本金和港币余额重置为0',
                            'carry_forward' => '删除全部余额结转记录',
                            'other_expenses' => '删除全部其他支出记录',
                        ])
                        ->columns(2),
                ])
                ->collapsible(),

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
        
        // 合并三个分类的选中值到统一的 content_types 数组（确保每个值都是数组）
        $accountTypes = $payload['content_types_accounts'] ?? [];
        $dataTypes = $payload['content_types_data'] ?? [];
        $baseTypes = $payload['content_types_base'] ?? [];
        $accountTypes = is_array($accountTypes) ? $accountTypes : [];
        $dataTypes = is_array($dataTypes) ? $dataTypes : [];
        $baseTypes = is_array($baseTypes) ? $baseTypes : [];
        $contentTypes = array_merge($accountTypes, $dataTypes, $baseTypes);
        
        // 检查是否选择了任何清理选项
        if (empty($contentTypes)) {
            Notification::make()
                ->title('请选择清理内容')
                ->warning()
                ->body('请至少选择一项要清理的数据类型')
                ->send();
            return;
        }
        
        // 构建传递给 CleanupService 的 payload（删除全部数据，不限时间范围）
        $cleanupPayload = [
            'time_range' => 'all',
            'content_types' => $contentTypes,
        ];
        
        $deleted = $service->cleanup($cleanupPayload, auth()->user()->name ?? 'system');

        // 格式化删除结果显示
        $resultText = $this->formatDeletedResult($deleted);
        
        Notification::make()
            ->title('清空成功')
            ->body($resultText)
            ->success()
            ->send();
            
        // 清空密码字段和选择
        $this->formData['verification_password'] = '';
    }
    
    /**
     * 格式化删除结果
     */
    private function formatDeletedResult(array $deleted): string
    {
        $labels = [
            'bills' => '交易记录',
            'drafts' => '交易草稿',
            'settlements' => '结算记录',
            'channels' => '渠道',
            'balances' => '渠道余额',
            'accounts' => '外勤账号',
            'locations' => '地点',
            'images' => '图片',
            'adjustments' => '调整记录',
            'carry_forward' => '余额结转',
            'other_expenses' => '其他支出',
            'statistics' => '统计数据',
            'audit_logs' => '审计日志',
            'notifications' => '通知',
        ];
        
        $results = [];
        foreach ($deleted as $key => $count) {
            if ($count > 0) {
                $label = $labels[$key] ?? $key;
                $results[] = "{$label}: {$count}条";
            }
        }
        
        // 检查是否选择了 adjustments（即使没有删除记录，也会重置本金和港币余额）
        $contentTypes = array_merge(
            $this->formData['content_types_accounts'] ?? [],
            $this->formData['content_types_data'] ?? [],
            $this->formData['content_types_base'] ?? []
        );
        
        if (in_array('adjustments', $contentTypes) && ($deleted['adjustments'] ?? 0) === 0) {
            $results[] = '本金和港币余额已重置为0';
        }
        
        return empty($results) ? '没有数据被删除' : implode('，', $results);
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
    
    /**
     * 一键全选所有清理选项
     */
    public function selectAllOptions(): void
    {
        $this->formData['content_types_accounts'] = ['accounts'];
        $this->formData['content_types_data'] = ['bills', 'drafts', 'settlements', 'images', 'statistics', 'audit_logs', 'notifications'];
        $this->formData['content_types_base'] = ['channels', 'locations', 'balances', 'adjustments', 'carry_forward', 'other_expenses'];
        
        Notification::make()
            ->title('已全选')
            ->success()
            ->duration(2000)
            ->send();
    }
    
    /**
     * 取消全选所有清理选项
     */
    public function clearAllOptions(): void
    {
        $this->formData['content_types_accounts'] = [];
        $this->formData['content_types_data'] = [];
        $this->formData['content_types_base'] = [];
        
        Notification::make()
            ->title('已取消全选')
            ->success()
            ->duration(2000)
            ->send();
    }
}


