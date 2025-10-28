<?php

namespace App\Filament\Resources\BalanceAdjustmentResource\Pages;

use App\Filament\Resources\BalanceAdjustmentResource;
use App\Models\Channel;
use App\Models\Setting;
use App\Models\HkdBalanceAdjustment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;

class ListBalanceAdjustments extends ListRecords
{
    protected static string $resource = BalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 系统港币余额调整按钮
            Actions\Action::make('adjust_system_hkd')
                ->label('调整系统港币余额')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->modalHeading('调整系统港币余额')
                ->modalDescription(fn () => '当前系统港币余额: HK$ ' . number_format(HkdBalanceAdjustment::getCurrentBalance(), 2))
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Section::make('当前港币余额')
                        ->schema([
                            Forms\Components\Placeholder::make('current_balance')
                                ->label('当前系统港币余额')
                                ->content(fn () => 'HK$ ' . number_format(HkdBalanceAdjustment::getCurrentBalance(), 2))
                                ->extraAttributes(['class' => 'text-2xl font-bold text-success-600']),
                        ]),
                    
                    Forms\Components\Section::make('调整信息')
                        ->schema([
                            Forms\Components\TextInput::make('before_amount')
                                ->label('调整前余额')
                                ->prefix('HK$')
                                ->numeric()
                                ->disabled()
                                ->default(fn () => HkdBalanceAdjustment::getCurrentBalance())
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
                                    $beforeAmount = $get('before_amount') ?? HkdBalanceAdjustment::getCurrentBalance();
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
                                
                            Forms\Components\Textarea::make('reason')
                                ->label('调整原因')
                                ->rows(3)
                                ->columnSpan('full')
                                ->placeholder('可选：请输入调整原因，例如：初始化港币余额、补充资金、修正错误等'),
                        ])->columns(2),
                ])
                ->action(function (array $data) {
                    try {
                        // 创建港币余额调整记录
                        HkdBalanceAdjustment::createAdjustment(
                            afterAmount: $data['after_amount'],
                            adjustmentType: 'manual',
                            reason: $data['reason'] ?? null,
                            settlementId: null,
                            userId: auth()->id()
                        );
                        
                        Notification::make()
                            ->title('调整成功')
                            ->body('系统港币余额已成功调整为 HK$ ' . number_format($data['after_amount'], 2))
                            ->success()
                            ->send();
                            
                        // 刷新页面
                        $this->redirect(static::getUrl());
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('调整失败')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isFinance()),
            
            // 创建渠道余额调整
            Actions\CreateAction::make()
                ->label('创建渠道余额调整')
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
            ->recordUrl(fn (Channel $record): string => 
                BalanceAdjustmentResource::getUrl('channel', ['channel' => $record->id])
            )
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
