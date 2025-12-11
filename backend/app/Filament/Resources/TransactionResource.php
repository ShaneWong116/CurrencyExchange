<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use App\Models\Channel;
use App\Models\FieldUser;
use App\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\ExportBulkAction;
use App\Filament\Exports\TransactionExporter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = '交易记录';
    protected static ?string $modelLabel = '交易记录';
    protected static ?string $pluralModelLabel = '交易记录';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationGroup = '交易管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('交易信息')
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
                                'instant_buyout' => '即时买断',
                                'exchange' => '兑换',
                            ])
                            ->required(),
                            
                        Forms\Components\Select::make('channel_id')
                            ->label('支付渠道')
                            ->options(Channel::where('status', 'active')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('location_id')
                            ->label('地点')
                            ->options(Location::where('status', 'active')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('若留空，将默认使用外勤人员所属地点')
                            ->native(false),
                    ])->columns(3),
                    
                Forms\Components\Section::make('金额信息')
                    ->schema([
                        Forms\Components\TextInput::make('rmb_amount')
                            ->label('人民币金额')
                            ->numeric()
                            ->required()
                            ->prefix('¥'),
                            
                        Forms\Components\TextInput::make('hkd_amount')
                            ->label('港币金额')
                            ->numeric()
                            ->required()
                            ->prefix('HK$'),
                            
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('交易汇率')
                            ->numeric()
                            ->required()
                            ->step(0.00001),
                            
                        Forms\Components\TextInput::make('instant_rate')
                            ->label('即时买断汇率')
                            ->numeric()
                            ->step(0.00001)
                            ->helperText('仅即时买断交易需要填写')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'instant_buyout'),
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
                            
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options([
                                'pending' => '处理中',
                                'success' => '成功',
                                'failed' => '失败',
                            ])
                            ->default('success')
                            ->required(),
                            
                        Forms\Components\DateTimePicker::make('submit_time')
                            ->label('提交时间')
                            ->default(now())
                            ->required(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('关联图片')
                    ->schema([
                        Forms\Components\Placeholder::make('images_display')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record || !$record->images || $record->images->isEmpty()) {
                                    return '暂无图片';
                                }
                                
                                $html = '<div style="display: flex; flex-wrap: wrap; gap: 12px;">';
                                foreach ($record->images as $image) {
                                    $url = route('api.images.show', $image->uuid);
                                    $html .= '<a href="' . $url . '" target="_blank" style="display: block;">';
                                    $html .= '<img src="' . $url . '" style="max-width: 200px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" />';
                                    $html .= '</a>';
                                }
                                $html .= '</div>';
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpan('full'),
                    ])
                    ->visible(fn ($record) => $record && $record->images && $record->images->count() > 0)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
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
                    
                TextColumn::make('instant_rate')
                    ->label('即时买断汇率')
                    ->numeric(5)
                    ->toggleable()
                    ->sortable()
                    ->placeholder('—')
                    ->tooltip('仅即时买断交易显示'),
                    
                TextColumn::make('channel.name')
                    ->label('支付渠道')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('地点')
                    ->toggleable(),
                    
                TextColumn::make('status')
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
                    
                TextColumn::make('settlement_status')
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
                    })
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
                        'instant_buyout' => '即时买断',
                        'exchange' => '兑换',
                    ]),
                    
                SelectFilter::make('channel')
                    ->label('支付渠道')
                    ->relationship('channel', 'name'),

                SelectFilter::make('location')
                    ->label('地点')
                    ->relationship('location', 'name'),
                    
                SelectFilter::make('user')
                    ->label('外勤人员')
                    ->relationship('user', 'name'),
                    
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'pending' => '处理中',
                        'success' => '成功',
                        'failed' => '失败',
                    ]),
                    
                SelectFilter::make('settlement_status')
                    ->label('结算状态')
                    ->options([
                        'unsettled' => '未结算',
                        'settled' => '已结算',
                    ]),
                    
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
            // 表格顶部汇总由 ListTransactions::getTableHeader() 渲染
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->hidden(fn (Transaction $record): bool => $record->isSettled()),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Transaction $record): bool => $record->isSettled())
                    ->requiresConfirmation()
                    ->modalHeading('删除交易记录')
                    ->modalDescription('确定要删除这条交易记录吗？删除后将自动回滚渠道余额。')
                    ->modalSubmitActionLabel('确认删除'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('批量删除交易记录')
                        ->modalDescription('确定要删除选中的交易记录吗？只有未结算的记录会被删除，已结算的记录会被跳过。')
                        ->modalSubmitActionLabel('确认删除')
                        ->deselectRecordsAfterCompletion(),
                    ExportBulkAction::make()
                        ->exporter(TransactionExporter::class),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}