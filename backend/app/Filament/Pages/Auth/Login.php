<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms;
use Filament\Forms\Components\Component;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('email')
            ->extraAttributes(['type' => 'text'])
            ->label('用户名')
            ->required()
            ->autofocus()
            // 登出后不保留账号回填
            ->autocomplete('off')
            ->extraInputAttributes(['autocomplete' => 'off']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return Forms\Components\TextInput::make('password')
            ->label('密码')
            ->password()
            ->required()
            // 登出后不保留密码回填（部分浏览器对 off 不严格，new-password 通常更有效）
            ->autocomplete('new-password')
            ->extraInputAttributes(['autocomplete' => 'new-password']);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
        ];
    }
}


