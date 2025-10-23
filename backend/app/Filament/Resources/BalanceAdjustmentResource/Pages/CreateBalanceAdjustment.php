<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\ChannelBalance;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateBalanceAdjustment extends CreateRecord
{
    protected static string $resource = BalanceAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['type'] = 'manual';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // 更新渠道余额
        $balance = ChannelBalance::getOrCreateTodayBalance(
            $record->channel_id, 
            $record->currency, 
            $record->before_amount
        );
        
        $balance->current_balance = $record->after_amount;
        $balance->save();
        
        $this->redirect($this->getResource()::getUrl('index'));
    }
}
