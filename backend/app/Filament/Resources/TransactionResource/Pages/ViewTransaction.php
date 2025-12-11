<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Support\HtmlString;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->hidden(fn () => $this->record->isSettled()),
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->isSettled()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('交易信息')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('外勤人员'),
                                TextEntry::make('type')
                                    ->label('交易类型')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'income' => 'success',
                                        'outcome' => 'danger',
                                        'instant_buyout' => 'warning',
                                        'exchange' => 'primary',
                                        default => 'secondary',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'income' => '入账',
                                        'outcome' => '出账',
                                        'instant_buyout' => '即时买断',
                                        'exchange' => '兑换',
                                        default => $state,
                                    }),
                                TextEntry::make('channel.name')
                                    ->label('支付渠道'),
                            ]),
                    ]),
                    
                Section::make('金额信息')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('rmb_amount')
                                    ->label('人民币金额')
                                    ->prefix('¥')
                                    ->numeric(2),
                                TextEntry::make('hkd_amount')
                                    ->label('港币金额')
                                    ->prefix('HK$')
                                    ->numeric(2),
                                TextEntry::make('exchange_rate')
                                    ->label('汇率')
                                    ->numeric(5),
                                TextEntry::make('instant_rate')
                                    ->label('即时买断汇率')
                                    ->numeric(5)
                                    ->placeholder('—')
                                    ->visible(fn ($record) => $record->type === 'instant_buyout'),
                            ]),
                    ]),
                    
                Section::make('其他信息')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('location.name')
                                    ->label('地点')
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('状态')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        default => 'secondary',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => '处理中',
                                        'success' => '成功',
                                        'failed' => '失败',
                                        default => $state,
                                    }),
                                TextEntry::make('settlement_status')
                                    ->label('结算状态')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'settled' => 'success',
                                        'unsettled' => 'gray',
                                        default => 'secondary',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'settled' => '已结算',
                                        'unsettled' => '未结算',
                                        default => $state,
                                    }),
                                TextEntry::make('created_at')
                                    ->label('创建时间')
                                    ->dateTime('Y-m-d H:i:s'),
                            ]),
                        TextEntry::make('notes')
                            ->label('备注')
                            ->placeholder('无备注')
                            ->columnSpan('full'),
                    ]),
                    
                Section::make('关联图片')
                    ->schema([
                        ViewEntry::make('images')
                            ->label('')
                            ->view('filament.infolists.components.transaction-images'),
                    ])
                    ->visible(fn ($record) => $record->images && $record->images->count() > 0)
                    ->collapsible(),
            ]);
    }
}
