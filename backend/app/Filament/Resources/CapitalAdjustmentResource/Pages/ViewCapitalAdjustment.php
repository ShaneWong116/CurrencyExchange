<?php

namespace App\Filament\Resources\CapitalAdjustmentResource\Pages;

use App\Filament\Resources\CapitalAdjustmentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ViewCapitalAdjustment extends ViewRecord
{
    protected static string $resource = CapitalAdjustmentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('本金调整信息')
                    ->schema([
                        TextEntry::make('before_amount')
                            ->label('调整前本金')
                            ->money('HKD'),
                            
                        TextEntry::make('adjustment_amount')
                            ->label('调整金额')
                            ->money('HKD')
                            ->color(fn ($record) => $record->adjustment_amount >= 0 ? 'success' : 'danger')
                            ->formatStateUsing(fn ($record) => 
                                ($record->adjustment_amount >= 0 ? '+' : '') . 
                                number_format($record->adjustment_amount, 2)
                            ),
                            
                        TextEntry::make('after_amount')
                            ->label('调整后本金')
                            ->money('HKD')
                            ->weight('bold'),
                    ])->columns(3),
                    
                Section::make('调整详情')
                    ->schema([
                        TextEntry::make('adjustment_type')
                            ->label('调整类型')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'manual' => 'warning',
                                'settlement' => 'success',
                                'system' => 'info',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'manual' => '手动调整',
                                'settlement' => '结算调整',
                                'system' => '系统调整',
                                default => $state,
                            }),
                            
                        TextEntry::make('settlement.settlement_number')
                            ->label('关联结算')
                            ->placeholder('无'),
                            
                        TextEntry::make('user.username')
                            ->label('操作人'),
                            
                        TextEntry::make('created_at')
                            ->label('调整时间')
                            ->dateTime('Y-m-d H:i:s'),
                            
                        TextEntry::make('reason')
                            ->label('调整原因')
                            ->columnSpan('full'),
                    ])->columns(2),
            ]);
    }
}

