<?php

namespace App\Filament\Resources\SettlementResource\Pages;

use App\Filament\Resources\SettlementResource;
use App\Services\SettlementService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ListSettlements extends ListRecords
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('execute_settlement')
                ->label('执行结余')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->modalHeading('执行结余操作')
                ->modalDescription('系统将根据当前未结余的交易计算利润并执行结余')
                ->form(function (SettlementService $settlementService) {
                    $preview = $settlementService->getPreview();
                    
                    return [
                        \Filament\Forms\Components\Section::make('结余预览')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('current_capital')
                                    ->label('当前本金')
                                    ->content(fn () => number_format($preview['current_capital'], 2) . ' HKD'),
                                
                                \Filament\Forms\Components\Placeholder::make('current_hkd_balance')
                                    ->label('当前港币结余')
                                    ->content(fn () => number_format($preview['current_hkd_balance'], 2) . ' HKD'),
                                
                                \Filament\Forms\Components\Placeholder::make('rmb_balance_total')
                                    ->label('人民币余额')
                                    ->content(fn () => number_format($preview['rmb_balance_total'], 2) . ' CNY'),
                            ])
                            ->columns(3),
                        
                        \Filament\Forms\Components\Section::make('计算结果')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('settlement_rate')
                                    ->label('结余汇率')
                                    ->content(fn () => number_format($preview['settlement_rate'], 3)),
                                
                                \Filament\Forms\Components\Placeholder::make('profit')
                                    ->label('利润')
                                    ->content(function () use ($preview) {
                                        $profit = $preview['profit'];
                                        $color = $profit >= 0 ? 'success' : 'danger';
                                        return new HtmlString("<span class='text-{$color}-600 font-bold'>" . 
                                               number_format($profit, 2) . ' HKD</span>');
                                    }),
                                
                                \Filament\Forms\Components\Placeholder::make('unsettled_count')
                                    ->label('未结余交易')
                                    ->content(fn () => '入账: ' . $preview['unsettled_income_count'] . ' 笔 | 出账: ' . $preview['unsettled_outcome_count'] . ' 笔'),
                            ])
                            ->columns(3),
                        
                        \Filament\Forms\Components\Section::make('预期结余后状态')
                            ->schema([
                                \Filament\Forms\Components\Placeholder::make('expected_new_capital')
                                    ->label('预期新本金（未扣除其他支出）')
                                    ->content(fn () => new HtmlString('<span class="text-success-600 font-bold text-lg">' . 
                                                     number_format($preview['expected_new_capital'], 2) . ' HKD</span>')),
                                
                                \Filament\Forms\Components\Placeholder::make('expected_new_hkd_balance')
                                    ->label('预期新港币结余')
                                    ->content(fn () => new HtmlString('<span class="text-success-600 font-bold text-lg">' . 
                                                     number_format($preview['expected_new_hkd_balance'], 2) . ' HKD</span>')),
                            ])
                            ->columns(2),
                        
                        \Filament\Forms\Components\Section::make('其他支出')
                            ->schema([
                                \Filament\Forms\Components\Repeater::make('expenses')
                                    ->label('支出明细')
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('item_name')
                                            ->label('支出项目')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('如：薪金、金流费用、电费等'),
                                        
                                        \Filament\Forms\Components\TextInput::make('amount')
                                            ->label('金额 (HKD)')
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix('HKD')
                                            ->default(0),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('添加支出项目')
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ]),
                        
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('备注')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ];
                })
                ->action(function (array $data, SettlementService $settlementService) {
                    try {
                        $settlement = $settlementService->execute(
                            $data['expenses'] ?? [],
                            $data['notes'] ?? null
                        );
                        
                        Notification::make()
                            ->title('结余成功')
                            ->success()
                            ->body("结余序号 #{$settlement->sequence_number}，利润: " . number_format($settlement->profit, 2) . ' HKD')
                            ->send();
                        
                        // 刷新页面
                        return redirect()->route('filament.admin.resources.settlements.view', ['record' => $settlement]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('结余失败')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('确认执行结余')
                ->modalCancelActionLabel('取消')
                ->before(function (SettlementService $settlementService) {
                    $preview = $settlementService->getPreview();
                    
                    if (!$preview['can_settle']) {
                        Notification::make()
                            ->title('无法执行结余')
                            ->warning()
                            ->body('当前没有未结余的交易，无法执行结余操作')
                            ->send();
                        
                        $this->halt();
                    }
                }),
        ];
    }
}

