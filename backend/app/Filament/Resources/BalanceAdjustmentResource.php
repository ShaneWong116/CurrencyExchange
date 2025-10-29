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
                Forms\Components\Section::make('调整分类')
                    ->schema([
                        Forms\Components\Select::make('adjustment_category')
                            ->label('调整类型')
                            ->options([
                                'capital' => '本金',
                                'channel' => '渠道余额',
                                'hkd_balance' => '港币余额',
                            ])
                            ->default('channel')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                // 重置相关字段
                                $set('channel_id', null);
                                $set('currency', null);
                                $set('before_amount', null);
                                $set('after_amount', null);
                                $set('adjustment_amount', null);
                                
                                // 根据分类设置默认值
                                if ($state === 'capital') {
                                    $set('currency', 'HKD');
                                    $set('before_amount', BalanceAdjustment::getCurrentCapital());
                                } elseif ($state === 'hkd_balance') {
                                    $set('currency', 'HKD');
                                    $set('before_amount', BalanceAdjustment::getCurrentHkdBalance());
                                }
                            }),
                            
                        Forms\Components\Placeholder::make('current_value_display')
                            ->label(fn (Forms\Get $get) => match($get('adjustment_category')) {
                                'capital' => '当前系统本金',
                                'hkd_balance' => '当前港币余额',
                                default => '',
                            })
                            ->content(fn (Forms\Get $get) => match($get('adjustment_category')) {
                                'capital' => 'HK$ ' . number_format(BalanceAdjustment::getCurrentCapital(), 2),
                                'hkd_balance' => 'HK$ ' . number_format(BalanceAdjustment::getCurrentHkdBalance(), 2),
                                default => '',
                            })
                            ->extraAttributes(['class' => 'text-2xl font-bold text-primary-600'])
                            ->visible(fn (Forms\Get $get) => in_array($get('adjustment_category'), ['capital', 'hkd_balance'])),
                    ])->columns(2),
                    
                Forms\Components\Section::make('渠道信息')
                    ->schema([
                        Forms\Components\Select::make('channel_id')
                            ->label('支付渠道')
                            ->options(Channel::active()->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, Forms\Get $get) {
                                if ($state) {
                                    $channel = Channel::find($state);
                                    if ($channel) {
                                        $set('rmb_current_balance', $channel->getRmbBalance());
                                        $set('hkd_current_balance', $channel->getHkdBalance());
                                        
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
                            ->live()
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
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('adjustment_category') === 'channel'),
                    
                Forms\Components\Section::make('调整详情')
                    ->schema([
                        Forms\Components\TextInput::make('before_amount')
                            ->label('调整前金额')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true)
                            ->suffix(fn (Forms\Get $get) => match($get('adjustment_category')) {
                                'capital', 'hkd_balance' => '港币',
                                'channel' => $get('currency') === 'RMB' ? '元' : '港币',
                                default => '',
                            })
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, Forms\Get $get) {
                                if ($state === null) {
                                    $category = $get('adjustment_category');
                                    if ($category === 'capital') {
                                        $component->state(BalanceAdjustment::getCurrentCapital());
                                    } elseif ($category === 'hkd_balance') {
                                        $component->state(BalanceAdjustment::getCurrentHkdBalance());
                                    }
                                }
                            }),
                            
                        Forms\Components\TextInput::make('after_amount')
                            ->label('调整后金额')
                            ->helperText('直接输入调整后的金额')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->suffix(fn (Forms\Get $get) => match($get('adjustment_category')) {
                                'capital', 'hkd_balance' => '港币',
                                'channel' => $get('currency') === 'RMB' ? '元' : '港币',
                                default => '',
                            })
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
                            
                        Forms\Components\Select::make('type')
                            ->label('调整类型')
                            ->options([
                                'manual' => '手动调整',
                                'system' => '系统调整',
                            ])
                            ->default('manual')
                            ->required()
                            ->disabled(fn (string $context) => $context === 'edit')
                            ->visible(fn (Forms\Get $get) => in_array($get('adjustment_category'), ['capital', 'hkd_balance'])),
                            
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
                    
                TextColumn::make('adjustment_category')
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
                    })
                    ->sortable(),
                    
                TextColumn::make('channel.name')
                    ->label('支付渠道')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                    
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
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('settlement.settlement_number')
                    ->label('关联结算')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                    
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
                SelectFilter::make('adjustment_category')
                    ->label('调整分类')
                    ->options([
                        'capital' => '本金',
                        'channel' => '渠道余额',
                        'hkd_balance' => '港币余额',
                    ]),
                    
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

    public static function canEdit($record): bool
    {
        return false; // 余额调整记录不允许编辑
    }

    public static function canDelete($record): bool
    {
        return false; // 余额调整记录不允许删除
    }

    public static function canViewAny(): bool
    {
        return \Illuminate\Support\Facades\Gate::allows('viewAny', \App\Models\BalanceAdjustment::class);
    }
}
