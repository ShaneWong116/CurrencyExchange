<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\BalanceAdjustment;
use App\Models\Channel;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ListBalanceAdjustments extends ListRecords
{
    protected static string $resource = BalanceAdjustmentResource::class;
    
    public function getTabs(): array
    {
        return [
            'capital' => Tab::make('本金')
                ->icon('heroicon-o-banknotes')
                ->badge(BalanceAdjustment::where('adjustment_category', 'capital')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('adjustment_category', 'capital')),
                
            'channel' => Tab::make('渠道余额')
                ->icon('heroicon-o-scale')
                ->badge(Channel::count()),
                // 不使用 modifyQueryUsing，因为渠道标签页使用不同的查询
                
            'hkd_balance' => Tab::make('港币余额')
                ->icon('heroicon-o-currency-dollar')
                ->badge(BalanceAdjustment::where('adjustment_category', 'hkd_balance')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('adjustment_category', 'hkd_balance')),
        ];
    }
    
    public function table(Table $table): Table
    {
        // 如果是渠道余额标签页，显示渠道列表
        if ($this->activeTab === 'channel') {
            return $table
                ->query(Channel::query())
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
                        ->toggleable(),
                        
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
                        
                    TextColumn::make('rmb_balance')
                        ->label('人民币余额')
                        ->prefix('¥')
                        ->numeric(2)
                        ->color(fn (Channel $record): string => $record->getRmbBalance() < 0 ? 'danger' : 'success')
                        ->weight('bold')
                        ->state(fn (Channel $record): float => $record->getRmbBalance()),
                        
                    TextColumn::make('hkd_balance')
                        ->label('港币余额')
                        ->prefix('HK$')
                        ->numeric(2)
                        ->color(fn (Channel $record): string => $record->getHkdBalance() < 0 ? 'danger' : 'success')
                        ->weight('bold')
                        ->state(fn (Channel $record): float => $record->getHkdBalance()),
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
                    Tables\Actions\Action::make('adjust_balance')
                        ->label('调整余额')
                        ->icon('heroicon-o-scale')
                        ->color('success')
                        ->url(fn (Channel $record): string => BalanceAdjustmentResource::getUrl('create', [
                            'adjustment_category' => 'channel',
                            'channel' => $record->id,
                        ]))
                        ->visible(fn () => \Illuminate\Support\Facades\Gate::allows('create', BalanceAdjustment::class)),
                        
                    Tables\Actions\Action::make('view_adjustments')
                        ->label('查看调整记录')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Channel $record): string => BalanceAdjustmentResource\Pages\ViewChannelBalance::getUrl(['channel' => $record->id])),
                ])
                ->recordUrl(fn (Channel $record): string => BalanceAdjustmentResource\Pages\ViewChannelBalance::getUrl(['channel' => $record->id]))
                ->defaultSort('id', 'asc');
        }
        
        // 其他标签页使用默认的资源表格
        return parent::table($table);
    }

    protected function getHeaderActions(): array
    {
        return [
            // 本金调整按钮
            Actions\Action::make('adjust_capital')
                ->label('调整系统本金')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->modalHeading('调整系统本金')
                ->modalDescription(fn () => '当前系统本金: HK$ ' . number_format(BalanceAdjustment::getCurrentCapital(), 2))
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Section::make('当前本金信息')
                        ->schema([
                            Forms\Components\Placeholder::make('current_capital')
                                ->label('当前系统本金')
                                ->content(fn () => 'HK$ ' . number_format(BalanceAdjustment::getCurrentCapital(), 2))
                                ->extraAttributes(['class' => 'text-2xl font-bold text-primary-600']),
                        ]),
                    
                    Forms\Components\Section::make('调整信息')
                        ->schema([
                            Forms\Components\TextInput::make('before_amount')
                                ->label('调整前本金')
                                ->prefix('HK$')
                                ->numeric()
                                ->disabled()
                                ->default(fn () => BalanceAdjustment::getCurrentCapital())
                                ->dehydrated(true),
                                
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
                                    $beforeAmount = $get('before_amount') ?? BalanceAdjustment::getCurrentCapital();
                                    if (is_numeric($state)) {
                                        $adjustmentAmount = floatval($state) - floatval($beforeAmount);
                                        $set('adjustment_amount', $adjustmentAmount);
                                    }
                                }),
                                
                            Forms\Components\Placeholder::make('adjustment_amount_display')
                                ->label('调整金额')
                                ->content(function (Forms\Get $get) {
                                    $amount = $get('adjustment_amount') ?? 0;
                                    $formatted = number_format(abs($amount), 2);
                                    $color = $amount >= 0 ? 'text-success-600' : 'text-danger-600';
                                    $sign = $amount >= 0 ? '+' : '-';
                                    return new \Illuminate\Support\HtmlString(
                                        "<span class='text-xl font-semibold {$color}'>{$sign} HK$ {$formatted}</span>"
                                    );
                                }),
                                
                            Forms\Components\Hidden::make('adjustment_amount'),
                            
                            Forms\Components\Select::make('type')
                                ->label('调整类型')
                                ->options([
                                    'manual' => '手动调整',
                                    'system' => '系统调整',
                                ])
                                ->default('manual')
                                ->required(),
                                
                            Forms\Components\Textarea::make('reason')
                                ->label('调整原因')
                                ->rows(3)
                                ->columnSpan('full')
                                ->placeholder('可选：请输入调整原因，例如：初始化系统本金、补充资本、利润提取等'),
                        ])->columns(2),
                ])
                ->action(function (array $data) {
                    try {
                        BalanceAdjustment::createCapitalAdjustment(
                            newAmount: $data['after_amount'],
                            type: $data['type'],
                            reason: $data['reason'] ?? '',
                            settlementId: null,
                            userId: auth()->id()
                        );
                        
                        Notification::make()
                            ->title('调整成功')
                            ->body('系统本金已成功调整为 HK$ ' . number_format($data['after_amount'], 2))
                            ->success()
                            ->send();
                            
                        $this->redirect(static::getUrl(['activeTab' => 'capital']));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('调整失败')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn () => $this->activeTab === 'capital' && ($u = auth()->user()) instanceof \App\Models\User && ($u->isAdmin() || $u->isFinance())),
            
            // 港币余额调整按钮
            Actions\Action::make('adjust_hkd_balance')
                ->label('调整港币余额')
                ->icon('heroicon-o-currency-dollar')
                ->color('info')
                ->modalHeading('调整系统港币余额')
                ->modalDescription(fn () => '当前港币余额: HK$ ' . number_format(BalanceAdjustment::getCurrentHkdBalance(), 2))
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Section::make('当前港币余额')
                        ->schema([
                            Forms\Components\Placeholder::make('current_balance')
                                ->label('当前系统港币余额')
                                ->content(fn () => 'HK$ ' . number_format(BalanceAdjustment::getCurrentHkdBalance(), 2))
                                ->extraAttributes(['class' => 'text-2xl font-bold text-info-600']),
                        ]),
                    
                    Forms\Components\Section::make('调整信息')
                        ->schema([
                            Forms\Components\TextInput::make('before_amount')
                                ->label('调整前余额')
                                ->prefix('HK$')
                                ->numeric()
                                ->disabled()
                                ->default(fn () => BalanceAdjustment::getCurrentHkdBalance())
                                ->dehydrated(true),
                                
                            Forms\Components\TextInput::make('after_amount')
                                ->label('调整后余额')
                                ->helperText('直接输入调整后的港币余额金额')
                                ->prefix('HK$')
                                ->numeric()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (callable $set, $state, Forms\Get $get) {
                                    if ($state === null || $state === '') {
                                        return;
                                    }
                                    $beforeAmount = $get('before_amount') ?? BalanceAdjustment::getCurrentHkdBalance();
                                    if (is_numeric($state)) {
                                        $adjustmentAmount = floatval($state) - floatval($beforeAmount);
                                        $set('adjustment_amount', $adjustmentAmount);
                                    }
                                }),
                                
                            Forms\Components\Placeholder::make('adjustment_amount_display')
                                ->label('调整金额')
                                ->content(function (Forms\Get $get) {
                                    $amount = $get('adjustment_amount') ?? 0;
                                    $formatted = number_format(abs($amount), 2);
                                    $color = $amount >= 0 ? 'text-success-600' : 'text-danger-600';
                                    $sign = $amount >= 0 ? '+' : '-';
                                    return new \Illuminate\Support\HtmlString(
                                        "<span class='text-xl font-semibold {$color}'>{$sign} HK$ {$formatted}</span>"
                                    );
                                }),
                                
                            Forms\Components\Hidden::make('adjustment_amount'),
                            
                            Forms\Components\Select::make('type')
                                ->label('调整类型')
                                ->options([
                                    'manual' => '手动调整',
                                    'system' => '系统调整',
                                ])
                                ->default('manual')
                                ->required(),
                                
                            Forms\Components\Textarea::make('reason')
                                ->label('调整原因')
                                ->rows(3)
                                ->columnSpan('full')
                                ->placeholder('可选：请输入调整原因，例如：初始化港币余额、补充资金、修正错误等'),
                        ])->columns(2),
                ])
                ->action(function (array $data) {
                    try {
                        BalanceAdjustment::createHkdBalanceAdjustment(
                            afterAmount: $data['after_amount'],
                            adjustmentType: $data['type'],
                            reason: $data['reason'] ?? null,
                            settlementId: null,
                            userId: auth()->id()
                        );
                        
                        Notification::make()
                            ->title('调整成功')
                            ->body('系统港币余额已成功调整为 HK$ ' . number_format($data['after_amount'], 2))
                            ->success()
                            ->send();
                            
                        $this->redirect(static::getUrl(['activeTab' => 'hkd_balance']));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('调整失败')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn () => $this->activeTab === 'hkd_balance' && ($u = auth()->user()) instanceof \App\Models\User && ($u->isAdmin() || $u->isFinance())),
            
            // 创建渠道余额调整
            Actions\CreateAction::make()
                ->label('创建渠道余额调整')
                ->icon('heroicon-o-scale')
                ->color('success')
                ->url(fn (): string => BalanceAdjustmentResource::getUrl('create', ['adjustment_category' => 'channel']))
                ->visible(fn () => $this->activeTab === 'channel' && \Illuminate\Support\Facades\Gate::allows('create', BalanceAdjustment::class)),
        ];
    }

}
