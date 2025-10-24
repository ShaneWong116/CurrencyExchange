<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\ChannelBalance;
use Filament\Resources\Pages\CreateRecord;
use Carbon\Carbon;

class CreateBalanceAdjustment extends CreateRecord
{
    protected static string $resource = BalanceAdjustmentResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // 如果URL中有channel参数，预选该渠道
        $channelId = request()->query('channel');
        if ($channelId) {
            $this->form->fill([
                'channel_id' => $channelId,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['type'] = 'manual';
        
        // 确保 adjustment_amount 被正确计算（如果前端没有计算）
        if (isset($data['after_amount']) && isset($data['before_amount'])) {
            $data['adjustment_amount'] = $data['after_amount'] - $data['before_amount'];
        }
        
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
        
        // 如果是从渠道详情页创建的，返回到渠道详情页
        $channelId = request()->query('channel');
        if ($channelId) {
            $this->redirect($this->getResource()::getUrl('channel', ['channel' => $channelId]));
        } else {
            $this->redirect($this->getResource()::getUrl('index'));
        }
    }
}
