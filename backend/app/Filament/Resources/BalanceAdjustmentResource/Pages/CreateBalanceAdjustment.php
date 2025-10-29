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
        
        // 如果URL中有 adjustment_category 参数，预填调整分类
        $adjustmentCategory = request()->query('adjustment_category');
        
        // 如果URL中有channel参数，预选该渠道
        $channelId = request()->query('channel');
        
        $fillData = [];
        
        if ($adjustmentCategory) {
            $fillData['adjustment_category'] = $adjustmentCategory;
        }
        
        if ($channelId) {
            $fillData['channel_id'] = $channelId;
        }
        
        if (!empty($fillData)) {
            $this->form->fill($fillData);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        
        // 如果没有设置 type，根据分类设置默认值
        if (!isset($data['type'])) {
            $data['type'] = 'manual';
        }
        
        // 根据分类设置货币类型
        if (in_array($data['adjustment_category'] ?? null, ['capital', 'hkd_balance'])) {
            $data['currency'] = 'HKD';
        }
        
        // 确保 adjustment_amount 被正确计算（如果前端没有计算）
        if (isset($data['after_amount']) && isset($data['before_amount'])) {
            $data['adjustment_amount'] = $data['after_amount'] - $data['before_amount'];
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // 根据调整分类执行不同的后续操作
        if ($record->adjustment_category === 'channel') {
            // 更新渠道余额
            $balance = ChannelBalance::getOrCreateTodayBalance(
                $record->channel_id, 
                $record->currency, 
                $record->before_amount
            );
            
            $balance->current_balance = $record->after_amount;
            $balance->save();
        } elseif ($record->adjustment_category === 'hkd_balance') {
            // 更新系统设置中的港币余额
            \App\Models\Setting::set('hkd_balance', $record->after_amount, '港币结余(HKD)', 'number');
        }
        // capital 类型不需要额外操作，因为从调整记录本身就能获取当前值
    }

    protected function getRedirectUrl(): string
    {
        // 如果是从渠道详情页创建的，返回到渠道详情页
        $channelId = request()->query('channel');
        if ($channelId) {
            return $this->getResource()::getUrl('channel', ['channel' => $channelId]);
        }
        
        // 默认返回列表页
        return $this->getResource()::getUrl('index');
    }
}
