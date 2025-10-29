<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;

class ViewBalanceAdjustment extends ViewRecord
{
    protected static string $resource = BalanceAdjustmentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('基本信息')
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('adjustment_category')
                            ->label('调整分类')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'capital' => 'primary',
                                'channel' => 'success',
                                'hkd_balance' => 'info',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'capital' => '本金',
                                'channel' => '渠道余额',
                                'hkd_balance' => '港币余额',
                                default => $state,
                            }),
                        TextEntry::make('channel.name')
                            ->label('支付渠道')
                            ->placeholder('-'),
                        TextEntry::make('currency')
                            ->label('货币类型')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'RMB' => 'success',
                                'HKD' => 'primary',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'RMB' => '人民币',
                                'HKD' => '港币',
                                default => $state,
                            }),
                    ])->columns(2),

                Section::make('调整详情')
                    ->schema([
                        TextEntry::make('before_amount')
                            ->label('调整前金额')
                            ->money(fn ($record) => $record->currency === 'RMB' ? 'CNY' : 'HKD'),
                        TextEntry::make('adjustment_amount')
                            ->label('调整金额')
                            ->money(fn ($record) => $record->currency === 'RMB' ? 'CNY' : 'HKD')
                            ->color(fn ($record): string => $record->adjustment_amount >= 0 ? 'success' : 'danger'),
                        TextEntry::make('after_amount')
                            ->label('调整后金额')
                            ->money(fn ($record) => $record->currency === 'RMB' ? 'CNY' : 'HKD')
                            ->weight('bold'),
                    ])->columns(3),

                Section::make('其他信息')
                    ->schema([
                        TextEntry::make('type')
                            ->label('调整类型')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'manual' => 'warning',
                                'system' => 'info',
                                default => 'secondary',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'manual' => '手动',
                                'system' => '系统',
                                default => $state,
                            }),
                        TextEntry::make('user.username')
                            ->label('操作人'),
                        TextEntry::make('settlement.settlement_number')
                            ->label('关联结算')
                            ->placeholder('-'),
                        TextEntry::make('reason')
                            ->label('调整原因')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('调整时间')
                            ->dateTime('Y-m-d H:i:s'),
                    ])->columns(3),
            ]);
    }
}
