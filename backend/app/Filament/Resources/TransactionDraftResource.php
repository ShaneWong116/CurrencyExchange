<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionDraftResource\Pages;
use App\Models\TransactionDraft;
use App\Models\Channel;
use App\Models\FieldUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class TransactionDraftResource extends Resource
{
    protected static ?string $model = TransactionDraft::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = '交易草稿';
    protected static ?string $modelLabel = '交易草稿';
    protected static ?string $pluralModelLabel = '交易草稿';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = '交易管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('草稿信息')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('外勤人员')
                            ->options(FieldUser::all()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                            
                        Forms\Components\Select::make('type')
                            ->label('交易类型')
                            ->options([
                                'income' => '入账',
                                'outcome' => '出账',
                                'exchange' => '兑换',
                            ])
                            ->required(),
                            
                        Forms\Components\Select::make('channel_id')
                            ->label('支付渠道')
                            ->options(Channel::where('status', 'active')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])->columns(3),
                    
                Forms\Components\Section::make('金额信息')
                    ->schema([
                        Forms\Components\TextInput::make('rmb_amount')
                            ->label('人民币金额')
                            ->numeric()
                            ->prefix('¥'),
                            
                        Forms\Components\TextInput::make('hkd_amount')
                            ->label('港币金额')
                            ->numeric()
                            ->prefix('HK$'),
                            
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('交易汇率')
                            ->numeric()
                            ->step(0.00001),
                            
                        Forms\Components\TextInput::make('instant_rate')
                            ->label('即时汇率')
                            ->numeric()
                            ->step(0.00001)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'exchange'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('其他信息')
                    ->schema([
                        Forms\Components\TextInput::make('location')
                            ->label('交易地点')
                            ->maxLength(200),
                            
                        Forms\Components\Textarea::make('notes')
                            ->label('备注')
                            ->rows(3)
                            ->columnSpan('full'),
                            
                        Forms\Components\DateTimePicker::make('last_modified')
                            ->label('最后修改时间')
                            ->default(now())
                            ->disabled(),
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
                    
                TextColumn::make('uuid')
                    ->label('草稿号')
                    ->limit(8)
                    ->tooltip(function (TextColumn $column): ?string {
                        return $column->getState();
                    })
                    ->searchable(),
                    
                TextColumn::make('user.name')
                    ->label('外勤人员')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('type')
                    ->label('交易类型')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'income' => 'success',
                        'outcome' => 'danger',
                        'exchange' => 'primary',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'income' => '入账',
                        'outcome' => '出账',
                        'exchange' => '兑换',
                        default => $state,
                    }),
                    
                TextColumn::make('rmb_amount')
                    ->label('人民币')
                    ->prefix('¥')
                    ->numeric(2)
                    ->sortable(),
                    
                TextColumn::make('hkd_amount')
                    ->label('港币')
                    ->prefix('HK$')
                    ->numeric(2)
                    ->sortable(),
                    
                TextColumn::make('exchange_rate')
                    ->label('汇率')
                    ->numeric(5)
                    ->sortable(),
                    
                TextColumn::make('channel.name')
                    ->label('支付渠道')
                    ->searchable()
                    ->sortable(),
                    
                TextColumn::make('location')
                    ->label('地点')
                    ->limit(20)
                    ->toggleable(),
                    
                TextColumn::make('last_modified')
                    ->label('最后修改')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                    
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('交易类型')
                    ->options([
                        'income' => '入账',
                        'outcome' => '出账',
                        'exchange' => '兑换',
                    ]),
                    
                SelectFilter::make('channel')
                    ->label('支付渠道')
                    ->relationship('channel', 'name'),
                    
                SelectFilter::make('user')
                    ->label('外勤人员')
                    ->relationship('user', 'name'),
                    
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('创建时间从'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('创建时间到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('submit')
                    ->label('提交为正式交易')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (TransactionDraft $record) {
                        $transaction = $record->convertToTransaction();
                        $record->delete();
                        
                        // 显示成功消息
                        \Filament\Notifications\Notification::make()
                            ->title('草稿已成功提交为正式交易')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('submit_all')
                        ->label('批量提交为正式交易')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->convertToTransaction();
                                $record->delete();
                                $count++;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("已成功提交 {$count} 条草稿为正式交易")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('last_modified', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactionDrafts::route('/'),
            'create' => Pages\CreateTransactionDraft::route('/create'),
            'view' => Pages\ViewTransactionDraft::route('/{record}'),
            'edit' => Pages\EditTransactionDraft::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isFinance();
    }
}