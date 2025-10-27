<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceAdjustmentResource\Pages;
use App\Models\BalanceAdjustment;
use App\Models\Channel;
use App\Models\ChannelBalance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\ExportBulkAction;
use App\Filament\Exports\BalanceAdjustmentExporter;

class BalanceAdjustmentResource extends Resource
{
    protected static ?string $model = BalanceAdjustment::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = '余额调整';
    protected static ?string $modelLabel = '余额调整';
    protected static ?string $pluralModelLabel = '余额调整';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = '财务管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('调整信息')
                    ->schema([
                        Forms\Components\Select::make('channel_id')
                            ->label('支付渠道')
                            ->options(Channel::active()->pluck('name', 'id'))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state, Forms\Get $get) {
                                if ($state) {
                                    $channel = Channel::find($state);
                                    if ($channel) {
                                        $set('rmb_current_balance', $channel->getRmbBalance());
                                        $set('hkd_current_balance', $channel->getHkdBalance());
                                        
                                        // 同时更新 before_amount
                                        $currency = $get('currency');
                                        if ($currency) {
                                            $currentBalance = $currency === 'RMB' 
                                                ? $channel->getRmbBalance() 
                                                : $channel->getHkdBalance();
                                            $set('before_amount', $currentBalance);
                                        }
                                    }
                                }
                            }),
                            
                        Forms\Components\Select::make('currency')
                            ->label('货币类型')
                            ->options([
                                'RMB' => '人民币',
                                'HKD' => '港币',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state, Forms\Get $get) {
                                $channelId = $get('channel_id');
                                if ($channelId && $state) {
                                    $channel = Channel::find($channelId);
                                    if ($channel) {
                                        $currentBalance = $state === 'RMB' 
                                            ? $channel->getRmbBalance() 
                                            : $channel->getHkdBalance();
                                        $set('before_amount', $currentBalance);
                                    }
                                }
                            }),
                            
                        Forms\Components\TextInput::make('rmb_current_balance')
                            ->label('当前人民币余额')
                            ->prefix('¥')
                            ->numeric()
                            ->disabled()
                            ->visible(fn (Forms\Get $get) => $get('currency') === 'RMB'),
                            
                        Forms\Components\TextInput::make('hkd_current_balance')
                            ->label('当前港币余额')
                            ->prefix('HK$')
                            ->numeric()
                            ->disabled()
                            ->visible(fn (Forms\Get $get) => $get('currency') === 'HKD'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('调整详情')
                    ->schema([
                        Forms\Components\TextInput::make('before_amount')
                            ->label('调整前金额')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->suffix(fn (Forms\Get $get) => $get('currency') === 'RMB' ? '元' : '港币'),
                            
                        Forms\Components\TextInput::make('after_amount')
                            ->label('调整后金额')
                            ->helperText('直接输入调整后的金额')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->suffix(fn (Forms\Get $get) => $get('currency') === 'RMB' ? '元' : '港币')
                            ->afterStateUpdated(function (callable $set, $state, Forms\Get $get) {
                                if ($state === null || $state === '') {
                                    return;
                                }
                                $beforeAmount = $get('before_amount');
                                if ($beforeAmount !== null && is_numeric($state)) {
                                    $adjustmentAmount = floatval($state) - floatval($beforeAmount);
                                    $set('adjustment_amount', $adjustmentAmount);
                                }
                            }),
                            
                        Forms\Components\Textarea::make('reason')
                            ->label('调整原因')
                            ->rows(3)
                            ->columnSpan('full')
                            ->placeholder('可选：请输入调整原因'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                TextColumn::make('channel.name')
                    ->label('支付渠道')
                    ->searchable()
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
                SelectFilter::make('channel')
                    ->label('支付渠道')
                    ->relationship('channel', 'name'),
                    
                SelectFilter::make('currency')
                    ->label('货币类型')
                    ->options([
                        'RMB' => '人民币',
                        'HKD' => '港币',
                    ]),
                    
                SelectFilter::make('type')
                    ->label('调整类型')
                    ->options([
                        'manual' => '手动',
                        'system' => '系统',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(BalanceAdjustmentExporter::class)
                        ->visible(fn () => ($u = auth()->user()) instanceof \App\Models\User && $u->canExportData()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceAdjustments::route('/'),
            'create' => Pages\CreateBalanceAdjustment::route('/create'),
            'view' => Pages\ViewBalanceAdjustment::route('/{record}'),
            'channel' => Pages\ViewChannelBalance::route('/channel/{channel}'),
        ];
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u instanceof \App\Models\User && ($u->isAdmin() || $u->isFinance());
    }

    public static function canViewAny(): bool
    {
        return \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\BalanceAdjustment::class);
    }
}
