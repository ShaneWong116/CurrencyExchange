<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return '创建后台账号';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('账号创建成功')
            ->body('新的后台账号已创建，用户可以使用该账号登录。');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 确保新账号只能是 finance 角色
        $data['role'] = 'finance';
        $data['status'] = $data['status'] ?? 'active';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        
        // 记录审计日志
        \App\Models\AuditLog::logAction(
            'user.created',
            $user,
            null,
            [
                'username' => $user->username,
                'role' => $user->role,
                'status' => $user->status,
            ]
        );
    }
}
