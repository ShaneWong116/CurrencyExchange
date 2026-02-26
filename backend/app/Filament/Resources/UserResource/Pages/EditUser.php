<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->record->isAdmin() ? '修改管理员密码' : '编辑后台账号';
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\ViewAction::make(),
        ];

        // 管理员账号不能删除
        if (!$this->record->isAdmin()) {
            $actions[] = Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('删除后台账号')
                ->modalDescription('确定要删除此账号吗？此操作不可恢复。')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('账号已删除')
                        ->body('后台账号已成功删除。')
                );
        }

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $message = $this->record->isAdmin() ? '密码已更新' : '账号更新成功';
        $body = $this->record->isAdmin() ? '管理员密码已成功修改。' : '后台账号信息已更新。';
        
        return Notification::make()
            ->success()
            ->title($message)
            ->body($body);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 防止修改管理员账号的角色和状态
        if ($this->record->isAdmin()) {
            $data['role'] = 'admin';
            $data['status'] = 'active';
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;
        $changes = $user->getChanges();
        
        if (empty($changes)) {
            return;
        }
        
        // 检查是否修改了密码
        $passwordChanged = isset($changes['password']);
        
        // 准备旧值和新值
        $oldValues = [];
        $newValues = [];
        
        foreach ($changes as $key => $newValue) {
            if ($key === 'password') {
                // 密码修改：只记录修改事实，不记录具体值
                $oldValues['password_changed'] = true;
                $newValues['password_changed'] = true;
                $newValues['changed_at'] = now()->toDateTimeString();
            } elseif ($key !== 'updated_at') {
                $oldValues[$key] = $user->getOriginal($key);
                $newValues[$key] = $newValue;
            }
        }
        
        // 记录审计日志
        $action = $passwordChanged ? 'user.password_changed' : 'user.updated';
        
        \App\Models\AuditLog::logAction(
            $action,
            $user,
            $oldValues,
            $newValues
        );
    }
}
