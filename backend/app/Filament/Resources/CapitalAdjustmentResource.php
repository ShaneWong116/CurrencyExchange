<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CapitalAdjustmentResource\Pages;
use App\Models\CapitalAdjustment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class CapitalAdjustmentResource extends Resource
{
    protected static ?string $model = CapitalAdjustment::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = '系统本金';
    protected static ?string $modelLabel = '本金调整';
    protected static ?string $pluralModelLabel = '本金调整';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = '财务管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('当前本金信息')
                    ->schema([
                        Forms\Components\Placeholder::make('current_capital_display')
                            ->label('当前系统本金')
                            ->content(fn () => 'HK$ ' . number_format(CapitalAdjustment::getCurrentCapital(), 2))
                            ->extraAttributes(['class' => 'text-2xl font-bold text-primary-600']),
                    ]),
                    
                Forms\Components\Section::make('本金调整')
                    ->schema([
                        Forms\Components\TextInput::make('before_amount')
                            ->label('调整前本金')
                            ->prefix('HK$')
                            ->numeric()
                            ->disabled()
                            ->default(fn () => CapitalAdjustment::getCurrentCapital())
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                if ($state === null) {
                                    $component->state(CapitalAdjustment::getCurrentCapital());
                                }
                            }),
                            
                        Forms\Components\TextInput::make('after_amount')
                            ->label('调整后本金')
                            ->helperText('直接输入调整后的本金金额（港币）')
                            ->prefix('HK$')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
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
                            
                        Forms\Components\Select::make('adjustment_type')
                            ->label('调整类型')
                            ->options([
                                'manual' => '手动调整',
                                'system' => '系统调整',
                            ])
                            ->default('manual')
                            ->required()
                            ->disabled(fn (string $context) => $context === 'edit'),
                            
                        Forms\Components\Textarea::make('reason')
                            ->label('调整原因')
                            ->rows(3)
                            ->columnSpan('full')
                            ->placeholder('可选：请输入调整原因，例如：初始化系统本金、补充资本、利润提取等'),
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
                    
                TextColumn::make('before_amount')
                    ->label('调整前本金')
                    ->numeric(2)
                    ->prefix('HK$')
                    ->sortable(),
                    
                TextColumn::make('adjustment_amount')
                    ->label('调整金额')
                    ->numeric(2)
                    ->prefix('HK$')
                    ->color(fn (CapitalAdjustment $record): string => $record->adjustment_amount >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (CapitalAdjustment $record): string => 
                        ($record->adjustment_amount >= 0 ? '+' : '') . number_format($record->adjustment_amount, 2)
                    )
                    ->sortable(),
                    
                TextColumn::make('after_amount')
                    ->label('调整后本金')
                    ->numeric(2)
                    ->prefix('HK$')
                    ->sortable()
                    ->weight('bold'),
                    
                TextColumn::make('adjustment_type')
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
                    
                TextColumn::make('settlement.settlement_number')
                    ->label('关联结算')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('无'),
                    
                TextColumn::make('user.username')
                    ->label('操作人')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('reason')
                    ->label('调整原因')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                    
                TextColumn::make('created_at')
                    ->label('调整时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('adjustment_type')
                    ->label('调整类型')
                    ->options([
                        'manual' => '手动调整',
                        'settlement' => '结算调整',
                        'system' => '系统调整',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // 本金调整不支持批量删除
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('current_capital')
                    ->label(fn () => '当前本金: HK$ ' . number_format(CapitalAdjustment::getCurrentCapital(), 2))
                    ->color('success')
                    ->disabled()
                    ->size('xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCapitalAdjustments::route('/'),
            'create' => Pages\CreateCapitalAdjustment::route('/create'),
            'view' => Pages\ViewCapitalAdjustment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u instanceof \App\Models\User && ($u->isAdmin() || $u->isFinance());
    }

    public static function canEdit($record): bool
    {
        return false; // 本金调整记录不允许编辑
    }

    public static function canDelete($record): bool
    {
        return false; // 本金调整记录不允许删除
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u instanceof \App\Models\User && ($u->isAdmin() || $u->isFinance() || $u->isFieldUser());
    }
}

