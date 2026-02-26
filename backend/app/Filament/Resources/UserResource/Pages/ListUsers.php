<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('新建账号')
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('账号创建成功')
                        ->body('新的后台账号已创建。')
                ),
        ];
    }

    public function getTitle(): string
    {
        return '后台账号管理';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
