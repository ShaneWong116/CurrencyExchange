<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\Channel;
use App\Models\BalanceAdjustment;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;

class ViewChannelBalance extends Page implements HasTable, HasInfolists
{
    use InteractsWithTable;
    use InteractsWithInfolists;

    protected static string $resource = BalanceAdjustmentResource::class;

    protected static string $view = 'filament.resources.balance-adjustment-resource.pages.view-channel-balance';

    public ?Channel $channel = null;

    public function mount(Channel $channel): void
    {
        $this->channel = $channel;
    }

    public function getTitle(): string
    {
        return $this->channel?->name . ' - 余额详情';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('返回列表')
                ->icon('heroicon-o-arrow-left')
                ->url(BalanceAdjustmentResource::getUrl('index')),
            
            Action::make('create_adjustment')
                ->label('修改余额')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => BalanceAdjustmentResource::getUrl('create', ['channel' => $this->channel->id]))
                ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('create', \App\Models\BalanceAdjustment::class)),
        ];
    }

    public function channelInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->channel)
            ->schema([
                Section::make('渠道信息')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('渠道名称'),
                                
                                TextEntry::make('code')
                                    ->label('渠道编码'),
                                
                                TextEntry::make('category')
                                    ->label('渠道类别')
                                    ->badge(),
                            ]),
                    ]),

                Section::make('当前余额')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('rmb_balance')
                                    ->label('人民币余额')
                                    ->state(fn (Channel $record): string => '¥' . number_format($record->getRmbBalance(), 2))
                                    ->color(fn (Channel $record): string => $record->getRmbBalance() < 0 ? 'danger' : 'success')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->weight('bold'),
                                
                                TextEntry::make('hkd_balance')
                                    ->label('港币余额')
                                    ->state(fn (Channel $record): string => 'HK$' . number_format($record->getHkdBalance(), 2))
                                    ->color(fn (Channel $record): string => $record->getHkdBalance() < 0 ? 'danger' : 'success')
                                    ->size(TextEntry\TextEntrySize::Large)
                                    ->weight('bold'),
                            ]),
                    ]),

                Section::make('统计信息')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_adjustments')
                                    ->label('总调整次数')
                                    ->state(fn (Channel $record): int => $record->balanceAdjustments()->count()),
                                
                                TextEntry::make('manual_adjustments')
                                    ->label('手动调整次数')
                                    ->state(fn (Channel $record): int => $record->balanceAdjustments()->manual()->count()),
                                
                                TextEntry::make('system_adjustments')
                                    ->label('系统调整次数')
                                    ->state(fn (Channel $record): int => $record->balanceAdjustments()->system()->count()),
                                
                                TextEntry::make('last_adjustment')
                                    ->label('最后调整时间')
                                    ->state(function (Channel $record): string {
                                        $lastAdjustment = $record->balanceAdjustments()
                                            ->latest('created_at')
                                            ->first();
                                        return $lastAdjustment ? $lastAdjustment->created_at->format('Y-m-d H:i:s') : '无';
                                    }),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BalanceAdjustment::query()
                    ->where('channel_id', $this->channel->id)
                    ->with(['user'])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('货币')
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

                TextColumn::make('before_amount')
                    ->label('调整前')
                    ->numeric(2)
                    ->prefix(fn (BalanceAdjustment $record): string => $record->currency === 'RMB' ? '¥' : 'HK$')
                    ->sortable(),

                TextColumn::make('adjustment_amount')
                    ->label('调整金额')
                    ->numeric(2)
                    ->prefix(fn (BalanceAdjustment $record): string => $record->currency === 'RMB' ? '¥' : 'HK$')
                    ->color(fn (BalanceAdjustment $record): string => $record->adjustment_amount >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (BalanceAdjustment $record): string => 
                        ($record->adjustment_amount >= 0 ? '+' : '') . number_format($record->adjustment_amount, 2)
                    )
                    ->sortable(),

                TextColumn::make('after_amount')
                    ->label('调整后')
                    ->numeric(2)
                    ->prefix(fn (BalanceAdjustment $record): string => $record->currency === 'RMB' ? '¥' : 'HK$')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('类型')
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

                TextColumn::make('user.username')
                    ->label('操作人')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('调整原因')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('created_at')
                    ->label('调整时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('currency')
                    ->label('货币类型')
                    ->options([
                        'RMB' => '人民币',
                        'HKD' => '港币',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('调整类型')
                    ->options([
                        'manual' => '手动',
                        'system' => '系统',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('调整记录')
            ->description('该渠道的所有余额调整记录');
    }
}

