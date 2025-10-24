<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\Channel;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ListBalanceAdjustments extends ListRecords
{
    protected static string $resource = BalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('create', \App\Models\BalanceAdjustment::class)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Models\Channel::query()->active())
            ->columns([
                TextColumn::make('name')
                    ->label('渠道名称')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('渠道编码')
                    ->searchable(),

                TextColumn::make('category')
                    ->label('渠道类别')
                    ->badge(),

                TextColumn::make('rmb_balance')
                    ->label('人民币余额')
                    ->state(function (Channel $record): float {
                        return $record->getRmbBalance();
                    })
                    ->numeric(2)
                    ->prefix('¥')
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('hkd_balance')
                    ->label('港币余额')
                    ->state(function (Channel $record): float {
                        return $record->getHkdBalance();
                    })
                    ->numeric(2)
                    ->prefix('HK$')
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),

                TextColumn::make('last_adjustment')
                    ->label('最后调整时间')
                    ->state(function (Channel $record): ?string {
                        $lastAdjustment = $record->balanceAdjustments()
                            ->latest('created_at')
                            ->first();
                        return $lastAdjustment ? $lastAdjustment->created_at->format('Y-m-d H:i') : '无';
                    })
                    ->default('无'),

                TextColumn::make('adjustments_count')
                    ->label('调整次数')
                    ->state(function (Channel $record): int {
                        return $record->balanceAdjustments()->count();
                    })
                    ->badge()
                    ->color('info'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('查看详情')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Channel $record): string => 
                        BalanceAdjustmentResource::getUrl('channel', ['channel' => $record->id])
                    ),
            ])
            ->filters([])
            ->bulkActions([]);
    }
}
