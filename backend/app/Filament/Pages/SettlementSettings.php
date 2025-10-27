<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SettlementSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = '结余设置';
    protected static ?string $title = '结余设置';
    protected static ?string $navigationGroup = '系统管理';
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.settlement-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'current_password' => null,
            'new_password' => null,
            'new_password_confirmation' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('修改结余确认密码')
                    ->description('请设置一个强密码以保护结余操作的安全性')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('当前密码')
                            ->password()
                            ->revealable()
                            ->required()
                            ->helperText('请输入当前的结余确认密码')
                            ->validationMessages([
                                'required' => '请输入当前密码',
                            ]),
                        
                        Forms\Components\TextInput::make('new_password')
                            ->label('新密码')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6)
                            ->different('current_password')
                            ->helperText('密码长度至少6位')
                            ->validationMessages([
                                'required' => '请输入新密码',
                                'min' => '密码长度至少6位',
                                'different' => '新密码不能与当前密码相同',
                            ]),
                        
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('确认新密码')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('new_password')
                            ->helperText('请再次输入新密码')
                            ->validationMessages([
                                'required' => '请确认新密码',
                                'same' => '两次输入的密码不一致',
                            ]),
                    ])
                    ->columns(1),
                
                Forms\Components\Section::make('密码提示')
                    ->description(function () {
                        $setting = Setting::where('key_name', 'settlement_password')->first();
                        
                        if (!$setting) {
                            return '⚠️ 未设置结余密码! 默认密码: 123456。请立即设置新密码以确保系统安全!';
                        }
                        
                        return '💡 密码安全建议: 使用至少6位字符,建议包含数字和字母,避免使用生日、电话等易猜密码,定期更换密码。';
                    })
                    ->schema([])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        
        try {
            // 1. 验证当前密码
            $setting = Setting::where('key_name', 'settlement_password')->first();
            
            if (!$setting) {
                // 如果不存在设置,检查是否使用默认密码
                if ($data['current_password'] !== '123456') {
                    Notification::make()
                        ->title('当前密码错误')
                        ->danger()
                        ->body('首次设置密码,请使用默认密码: 123456')
                        ->send();
                    return;
                }
                
                // 创建新的密码设置
                $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
                Setting::create([
                    'key_name' => 'settlement_password',
                    'key_value' => $hashedPassword,
                    'description' => '结余确认密码(哈希加密)',
                    'type' => 'string',
                ]);
                
                Notification::make()
                    ->title('密码设置成功')
                    ->success()
                    ->body('结余确认密码已成功设置')
                    ->send();
            } else {
                // 验证当前密码
                if (!password_verify($data['current_password'], $setting->key_value)) {
                    Notification::make()
                        ->title('当前密码错误')
                        ->danger()
                        ->body('请输入正确的当前密码')
                        ->send();
                    return;
                }
                
                // 更新密码
                $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $setting->key_value = $hashedPassword;
                $setting->save();
                
                Notification::make()
                    ->title('密码修改成功')
                    ->success()
                    ->body('结余确认密码已成功修改')
                    ->send();
            }
            
            // 清空表单
            $this->form->fill();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('操作失败')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('submit')
                ->label('保存修改')
                ->submit('submit')
                ->color('success'),
        ];
    }
}

