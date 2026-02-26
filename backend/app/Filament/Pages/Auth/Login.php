<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Illuminate\Validation\ValidationException;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

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

    /**
     * 重写认证方法，添加登录时间记录
     */
    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'email' => __('filament-panels::pages/auth/login.messages.throttled', [
                    'seconds' => $exception->secondsUntilAvailable,
                    'minutes' => ceil($exception->secondsUntilAvailable / 60),
                ]),
            ]);
        }

        $data = $this->form->getState();

        if (! \Filament\Facades\Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            throw ValidationException::withMessages([
                'email' => __('filament-panels::pages/auth/login.messages.failed'),
            ]);
        }

        // 更新最后登录时间
        $user = \Filament\Facades\Filament::auth()->user();
        if ($user) {
            $user->update([
                'last_login_at' => now(),
            ]);
        }

        session()->regenerate();

        return app(\Filament\Http\Responses\Auth\Contracts\LoginResponse::class);
    }
}

