<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChannelResource\Pages;
use App\Models\Channel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = '支付渠道';
    protected static ?string $modelLabel = '支付渠道';
    protected static ?string $pluralModelLabel = '支付渠道';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationGroup = '系统管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('渠道信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('渠道名称')
                            ->required()
                            ->maxLength(100),
                            
                        Forms\Components\TextInput::make('code')
                            ->label('渠道代码')
                            ->required()
                            ->unique(Channel::class, 'code', ignoreRecord: true)
                            ->maxLength(50),
                            
                        Forms\Components\TextInput::make('label')
                            ->label('标签')
                            ->maxLength(100)
                            ->placeholder('如：线上、线下、第三方'),
                    ])->columns(3),
                    
                Forms\Components\Section::make('分类设置')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->label('分类')
                            ->options([
                                'bank' => '银行',
                                'ewallet' => '电子钱包',
                                'cash' => '现金',
                                'other' => '其他',
                            ])
                            ->required()
                            ->default('other'),
                            
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                'active' => '启用',
                                'inactive' => '停用',
                            ])
                            ->required()
                            ->default('active'),
                            
                        Forms\Components\TextInput::make('transaction_count')
                            ->label('累计交易次数')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                TextColumn::make('name')
                    ->label('渠道名称')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('code')
                    ->label('渠道代码')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('label')
                    ->label('标签')
                    ->toggleable(),
                    
                TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank' => 'success',
                        'ewallet' => 'warning',
                        'cash' => 'info',
                        'other' => 'secondary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank' => '银行',
                        'ewallet' => '电子钱包',
                        'cash' => '现金',
                        'other' => '其他',
                        default => $state,
                    }),
                    
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? '启用' : '停用'),
                    
                TextColumn::make('transaction_count')
                    ->label('累计交易')
                    ->suffix(' 笔')
                    ->sortable(),
                    
                TextColumn::make('rmb_balance')
                    ->label('人民币余额')
                    ->prefix('¥')
                    ->numeric(2)
                    ->state(fn (Channel $record): float => $record->getRmbBalance()),
                    
                TextColumn::make('hkd_balance')
                    ->label('港币余额')
                    ->prefix('HK$')
                    ->numeric(2)
                    ->state(fn (Channel $record): float => $record->getHkdBalance()),
                    
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('分类')
                    ->options([
                        'bank' => '银行',
                        'ewallet' => '电子钱包',
                        'cash' => '现金',
                        'other' => '其他',
                    ]),
                    
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'active' => '启用',
                        'inactive' => '停用',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn() => auth()->user()->canManageChannels()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => auth()->user()->canManageChannels()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->canManageChannels()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChannels::route('/'),
            'create' => Pages\CreateChannel::route('/create'),
            'view' => Pages\ViewChannel::route('/{record}'),
            'edit' => Pages\EditChannel::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->canManageChannels();
    }
}