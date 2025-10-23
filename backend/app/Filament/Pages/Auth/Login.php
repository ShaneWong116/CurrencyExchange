<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('email')
            ->extraAttributes(['type' => 'text'])
            ->label('用户名')
            ->required()
            ->autofocus()
            ->autocomplete('username');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
        ];
    }
}


