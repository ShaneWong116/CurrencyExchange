<?php

namespace App\Filament\Resources\CapitalAdjustmentResource\Pages;

use App\Filament\Resources\CapitalAdjustmentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\CapitalAdjustment;
use Filament\Notifications\Notification;

class CreateCapitalAdjustment extends CreateRecord
{
    protected static string $resource = CapitalAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 获取当前本金
        $currentCapital = CapitalAdjustment::getCurrentCapital();
        
        // 确保 before_amount 是当前本金
        $data['before_amount'] = $currentCapital;
        
        // 计算调整金额
        $data['adjustment_amount'] = $data['after_amount'] - $currentCapital;
        
        // 设置操作人
        $data['user_id'] = auth()->id();
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('本金调整成功')
            ->body('系统本金已更新为 HK$ ' . number_format($this->record->after_amount, 2));
    }
}

