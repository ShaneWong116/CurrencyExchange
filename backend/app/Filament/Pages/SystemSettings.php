<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SystemSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = '系统设置';
    protected static ?string $title = '系统设置';
    protected static ?string $navigationGroup = '系统管理';
    protected static ?int $navigationSort = 98;

    protected static string $view = 'filament.pages.system-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // 数据清理密码
            'cleanup_current_password' => null,
            'cleanup_new_password' => null,
            'cleanup_new_password_confirmation' => null,
            
            // 结余密码
            'settlement_current_password' => null,
            'settlement_new_password' => null,
            'settlement_new_password_confirmation' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('数据清理二次验证密码')
                    ->description('设置数据清理操作的验证密码，以防止误操作')
                    ->schema([
                        Forms\Components\TextInput::make('cleanup_current_password')
                            ->label('当前密码')
                            ->password()
                            ->revealable()
                            ->helperText('请输入当前的数据清理验证密码')
                            ->validationMessages([
                                'required' => '请输入当前密码',
                            ]),
                        
                        Forms\Components\TextInput::make('cleanup_new_password')
                            ->label('新密码')
                            ->password()
                            ->revealable()
                            ->minLength(6)
                            ->different('cleanup_current_password')
                            ->helperText('密码长度至少6位')
                            ->validationMessages([
                                'required' => '请输入新密码',
                                'min' => '密码长度至少6位',
                                'different' => '新密码不能与当前密码相同',
                            ]),
                        
                        Forms\Components\TextInput::make('cleanup_new_password_confirmation')
                            ->label('确认新密码')
                            ->password()
                            ->revealable()
                            ->same('cleanup_new_password')
                            ->helperText('请再次输入新密码')
                            ->validationMessages([
                                'required' => '请确认新密码',
                                'same' => '两次输入的密码不一致',
                            ]),
                        
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('update_cleanup_password')
                                ->label('更新数据清理密码')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalHeading('确认更新数据清理密码?')
                                ->modalDescription('此操作将修改数据清理的验证密码')
                                ->action('updateCleanupPassword'),
                        ]),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                Forms\Components\Section::make('结余确认密码')
                    ->description('设置结余操作的确认密码，以保护结余数据安全')
                    ->schema([
                        Forms\Components\TextInput::make('settlement_current_password')
                            ->label('当前密码')
                            ->password()
                            ->revealable()
                            ->helperText('请输入当前的结余确认密码')
                            ->validationMessages([
                                'required' => '请输入当前密码',
                            ]),
                        
                        Forms\Components\TextInput::make('settlement_new_password')
                            ->label('新密码')
                            ->password()
                            ->revealable()
                            ->minLength(6)
                            ->different('settlement_current_password')
                            ->helperText('密码长度至少6位')
                            ->validationMessages([
                                'required' => '请输入新密码',
                                'min' => '密码长度至少6位',
                                'different' => '新密码不能与当前密码相同',
                            ]),
                        
                        Forms\Components\TextInput::make('settlement_new_password_confirmation')
                            ->label('确认新密码')
                            ->password()
                            ->revealable()
                            ->same('settlement_new_password')
                            ->helperText('请再次输入新密码')
                            ->validationMessages([
                                'required' => '请确认新密码',
                                'same' => '两次输入的密码不一致',
                            ]),
                        
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('update_settlement_password')
                                ->label('更新结余确认密码')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('确认更新结余确认密码?')
                                ->modalDescription('此操作将修改结余操作的确认密码')
                                ->action('updateSettlementPassword'),
                        ]),
                    ])
                    ->columns(1)
                    ->collapsible(),
                
                Forms\Components\Section::make('安全提示')
                    ->description(function () {
                        $cleanupSetting = Setting::where('key_name', 'cleanup_password')->first();
                        $settlementSetting = Setting::where('key_name', 'settlement_password')->first();
                        
                        $warnings = [];
                        if (!$cleanupSetting) {
                            $warnings[] = '⚠️ 未设置数据清理密码! 默认密码: 123456';
                        }
                        if (!$settlementSetting) {
                            $warnings[] = '⚠️ 未设置结余密码! 默认密码: 123456';
                        }
                        
                        if (!empty($warnings)) {
                            return implode("\n", $warnings) . "\n\n请立即设置新密码以确保系统安全!";
                        }
                        
                        return '💡 密码安全建议: 使用至少6位字符，建议包含数字和字母，避免使用生日、电话等易猜密码，定期更换密码。';
                    })
                    ->schema([])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    /**
     * 更新数据清理密码
     */
    public function updateCleanupPassword(): void
    {
        $data = $this->form->getState();
        
        // 验证必填字段
        if (empty($data['cleanup_current_password']) || 
            empty($data['cleanup_new_password']) || 
            empty($data['cleanup_new_password_confirmation'])) {
            Notification::make()
                ->title('操作失败')
                ->danger()
                ->body('请填写所有必填字段')
                ->send();
            return;
        }
        
        try {
            $this->updatePassword('cleanup_password', 
                $data['cleanup_current_password'], 
                $data['cleanup_new_password'],
                $data['cleanup_new_password_confirmation'],
                '数据清理验证密码');
            
            // 清空表单
            $this->form->fill([
                'cleanup_current_password' => null,
                'cleanup_new_password' => null,
                'cleanup_new_password_confirmation' => null,
            ]);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('操作失败')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * 更新结余确认密码
     */
    public function updateSettlementPassword(): void
    {
        $data = $this->form->getState();
        
        // 验证必填字段
        if (empty($data['settlement_current_password']) || 
            empty($data['settlement_new_password']) || 
            empty($data['settlement_new_password_confirmation'])) {
            Notification::make()
                ->title('操作失败')
                ->danger()
                ->body('请填写所有必填字段')
                ->send();
            return;
        }
        
        try {
            $this->updatePassword('settlement_password', 
                $data['settlement_current_password'], 
                $data['settlement_new_password'],
                $data['settlement_new_password_confirmation'],
                '结余确认密码');
            
            // 清空表单
            $this->form->fill([
                'settlement_current_password' => null,
                'settlement_new_password' => null,
                'settlement_new_password_confirmation' => null,
            ]);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('操作失败')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * 通用密码更新方法
     */
    private function updatePassword(
        string $keyName, 
        string $currentPassword, 
        string $newPassword,
        string $confirmPassword,
        string $passwordLabel
    ): void {
        // 验证新密码确认
        if ($newPassword !== $confirmPassword) {
            throw new \Exception('两次输入的新密码不一致');
        }
        
        // 验证新密码长度
        if (strlen($newPassword) < 6) {
            throw new \Exception('新密码长度至少6位');
        }
        
        // 验证新密码不能与当前密码相同
        if ($currentPassword === $newPassword) {
            throw new \Exception('新密码不能与当前密码相同');
        }
        
        // 获取设置
        $setting = Setting::where('key_name', $keyName)->first();
        
        if (!$setting) {
            // 如果不存在设置，检查是否使用默认密码
            if ($currentPassword !== '123456') {
                throw new \Exception('当前密码错误。首次设置密码，请使用默认密码: 123456');
            }
            
            // 创建新的密码设置
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            Setting::create([
                'key_name' => $keyName,
                'key_value' => $hashedPassword,
                'description' => $passwordLabel . '(哈希加密)',
                'type' => 'string',
            ]);
            
            Notification::make()
                ->title('密码设置成功')
                ->success()
                ->body($passwordLabel . '已成功设置')
                ->send();
        } else {
            // 验证当前密码
            if (!password_verify($currentPassword, $setting->key_value)) {
                throw new \Exception('当前密码错误，请输入正确的当前密码');
            }
            
            // 更新密码
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $setting->key_value = $hashedPassword;
            $setting->save();
            
            Notification::make()
                ->title('密码修改成功')
                ->success()
                ->body($passwordLabel . '已成功修改')
                ->send();
        }
    }
}

