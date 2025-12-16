<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettlementResource\Pages;
use App\Models\Settlement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = '结余管理';

    protected static ?string $modelLabel = '结余记录';

    protected static ?string $pluralModelLabel = '结余记录';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('结余信息')
                    ->schema([
                        Forms\Components\TextInput::make('sequence_number')
                            ->label('结余序号')
                            ->disabled()
                            ->default(fn () => Settlement::getNextSequenceNumber()),
                        
                        Forms\Components\Textarea::make('notes')
                            ->label('备注')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sequence_number')
                    ->label('序号')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('previous_capital')
                    ->label('结余前本金')
                    ->money('HKD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('outgoing_profit')
                    ->label('出账利润')
                    ->money('HKD')
                    ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('instant_profit')
                    ->label('即时买断利润')
                    ->money('HKD')
                    ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('profit')
                    ->label('总利润')
                    ->money('HKD')
                    ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('other_expenses_total')
                    ->label('其他支出')
                    ->money('HKD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('other_incomes_total')
                    ->label('其他收入')
                    ->money('HKD')
                    ->color('success')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('new_capital')
                    ->label('结余后本金')
                    ->money('HKD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('settlement_rate')
                    ->label('结余汇率')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('instant_buyout_rate')
                    ->label('即时买断汇率')
                    ->numeric(decimalPlaces: 3)
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('rmb_balance_total')
                    ->label('人民币余额')
                    ->money('CNY')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('关联交易数')
                    ->counts('transactions')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('creator_name')
                    ->label('操作人')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function (Settlement $record) {
                        return $record->creator_name;
                    }),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('结余时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('sequence_number', 'desc')
            ->filters([
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('开始日期'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('结束日期'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // 不允许批量删除结余记录
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('结余概览')
                    ->schema([
                        Infolists\Components\TextEntry::make('sequence_number')
                            ->label('结余序号')
                            ->badge()
                            ->color('primary'),
                        
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('结余时间')
                            ->dateTime('Y-m-d H:i:s'),
                        
                        Infolists\Components\TextEntry::make('settlement_rate')
                            ->label('结余汇率')
                            ->badge()
                            ->color('info'),
                        
                        Infolists\Components\TextEntry::make('creator_name')
                            ->label('操作人')
                            ->badge()
                            ->color('gray')
                            ->getStateUsing(function (Settlement $record) {
                                return $record->creator_name;
                            }),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('利润明细')
                    ->schema([
                        Infolists\Components\TextEntry::make('outgoing_profit')
                            ->label('出账利润')
                            ->money('HKD')
                            ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger'),
                        
                        Infolists\Components\TextEntry::make('instant_profit')
                            ->label('即时买断利润')
                            ->money('HKD')
                            ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger'),
                        
                        Infolists\Components\TextEntry::make('profit')
                            ->label('总利润')
                            ->money('HKD')
                            ->color(fn (string $state): string => $state >= 0 ? 'success' : 'danger')
                            ->weight('bold'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('本金变化')
                    ->schema([
                        Infolists\Components\TextEntry::make('previous_capital')
                            ->label('结余前本金')
                            ->money('HKD')
                            ->size('lg'),
                        
                        Infolists\Components\TextEntry::make('other_expenses_total')
                            ->label('其他支出')
                            ->money('HKD')
                            ->color('danger')
                            ->size('lg'),
                        
                        Infolists\Components\TextEntry::make('other_incomes_total')
                            ->label('其他收入')
                            ->money('HKD')
                            ->color('success')
                            ->size('lg'),
                        
                        Infolists\Components\TextEntry::make('new_capital')
                            ->label('结余后本金')
                            ->money('HKD')
                            ->color('success')
                            ->size('lg')
                            ->weight('bold'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('港币结余变化')
                    ->schema([
                        Infolists\Components\TextEntry::make('previous_hkd_balance')
                            ->label('结余前港币结余')
                            ->money('HKD'),
                        
                        Infolists\Components\TextEntry::make('new_hkd_balance')
                            ->label('结余后港币结余')
                            ->money('HKD')
                            ->color('success')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('rmb_balance_total')
                            ->label('人民币余额总计')
                            ->money('CNY'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('其他支出明细')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('expenses')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('type')
                                    ->label('类型')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'income' ? 'success' : 'danger')
                                    ->formatStateUsing(fn (string $state): string => $state === 'income' ? '收入' : '支出'),
                                Infolists\Components\TextEntry::make('item_name')
                                    ->label('项目名称'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('金额')
                                    ->money('HKD'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Settlement $record) => $record->expenses->count() > 0),

                Infolists\Components\Section::make('关联交易')
                    ->schema([
                        Infolists\Components\TextEntry::make('transactions_count')
                            ->label('总交易数')
                            ->state(fn (Settlement $record) => $record->transactions->count())
                            ->badge(),
                        
                        Infolists\Components\TextEntry::make('income_count')
                            ->label('入账交易数')
                            ->state(fn (Settlement $record) => $record->transactions->where('type', 'income')->count())
                            ->badge()
                            ->color('success'),
                        
                        Infolists\Components\TextEntry::make('outcome_count')
                            ->label('出账交易数')
                            ->state(fn (Settlement $record) => $record->transactions->where('type', 'outcome')->count())
                            ->badge()
                            ->color('warning'),
                        
                        Infolists\Components\TextEntry::make('instant_buyout_count')
                            ->label('即时买断交易数')
                            ->state(fn (Settlement $record) => $record->transactions->where('type', 'instant_buyout')->count())
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('备注')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('')
                            ->placeholder('无备注')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Settlement $record) => !empty($record->notes)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettlements::route('/'),
            'create' => Pages\CreateSettlement::route('/create'),
            'view' => Pages\ViewSettlement::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        // 通过自定义页面执行结余操作
        return false;
    }

    public static function canEdit($record): bool
    {
        // 结余记录不可编辑
        return false;
    }

    public static function canDelete($record): bool
    {
        // 结余记录不可删除
        return false;
    }
}

