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
                ->steps([
                    // 第一步：填写支出并预览
                    \Filament\Forms\Components\Wizard\Step::make('填写支出')
                        ->schema(function (SettlementService $settlementService) {
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
                                        
                                        \Filament\Forms\Components\Placeholder::make('outgoing_profit')
                                            ->label('利润（未扣除支出）')
                                            ->content(function () use ($preview) {
                                                $outgoingProfit = $preview['outgoing_profit'];
                                                $instantProfit = $preview['instant_profit'];
                                                $totalProfit = $preview['profit'];
                                                $color = $totalProfit >= 0 ? 'success' : 'danger';
                                                
                                                return new HtmlString(
                                                    "<div class='space-y-1'>" .
                                                    "<div>出账利润: <span class='font-semibold'>" . number_format($outgoingProfit, 0) . " HKD</span></div>" .
                                                    "<div>即时买断利润: <span class='font-semibold'>" . number_format($instantProfit, 0) . " HKD</span></div>" .
                                                    "<div class='border-t pt-1 mt-1'>总利润: <span class='text-{$color}-600 font-bold'>" . number_format($totalProfit, 0) . " HKD</span></div>" .
                                                    "</div>"
                                                );
                                            }),
                                        
                                        \Filament\Forms\Components\Placeholder::make('unsettled_count')
                                            ->label('未结余交易')
                                            ->content(function () use ($preview) {
                                                $html = "<div class='space-y-1'>";
                                                $html .= "<div>入账: <span class='font-semibold'>" . $preview['unsettled_income_count'] . " 笔</span></div>";
                                                $html .= "<div>出账: <span class='font-semibold'>" . $preview['unsettled_outcome_count'] . " 笔</span></div>";
                                                
                                                if ($preview['unsettled_instant_count'] > 0) {
                                                    $html .= "<div class='text-orange-600'>即时买断: <span class='font-semibold'>" . $preview['unsettled_instant_count'] . " 笔</span></div>";
                                                }
                                                
                                                $html .= "</div>";
                                                return new HtmlString($html);
                                            }),
                                    ])
                                    ->columns(3),
                                
                                \Filament\Forms\Components\Section::make('其他支出')
                                    ->schema([
                                        \Filament\Forms\Components\Repeater::make('expenses')
                                            ->label('支出明细')
                                            ->schema([
                                                \Filament\Forms\Components\TextInput::make('item_name')
                                                    ->label('支出项目')
                                                    ->required()
                                                    ->maxLength(100)
                                                    ->placeholder('如：薪金、金流费用、电费等')
                                                    ->validationMessages([
                                                        'required' => '请输入支出项目名称',
                                                        'max' => '支出项目名称不能超过 100 个字符',
                                                    ]),
                                                
                                                \Filament\Forms\Components\TextInput::make('amount')
                                                    ->label('金额 (HKD)')
                                                    ->required()
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('HKD')
                                                    ->default(0)
                                                    ->validationMessages([
                                                        'required' => '请输入支出金额',
                                                        'numeric' => '支出金额必须是数字',
                                                        'min' => '支出金额不能小于 0',
                                                    ]),
                                            ])
                                            ->columns(2)
                                            ->addActionLabel('添加支出项目')
                                            ->collapsible()
                                            ->defaultItems(0)
                                            ->columnSpanFull()
                                            ->live(),
                                    ]),
                                
                                \Filament\Forms\Components\Textarea::make('notes')
                                    ->label('备注')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ];
                        }),
                    
                    // 第二步：确认最终结果并输入密码
                    \Filament\Forms\Components\Wizard\Step::make('确认结余')
                        ->schema(function (SettlementService $settlementService, callable $get) {
                            $preview = $settlementService->getPreview();
                            $expenses = $get('expenses') ?? [];
                            $recommendation = $settlementService->getRecommendedSettlementDate();
                            $usedDates = $settlementService->getUsedSettlementDates();
                            
                            // 计算总支出
                            $totalExpenses = collect($expenses)->sum('amount');
                            
                            // 计算扣除支出后的本金和利润
                            $finalCapital = $preview['expected_new_capital'] - $totalExpenses;
                            $finalProfit = $preview['profit'] - $totalExpenses;
                            
                            return [
                                \Filament\Forms\Components\Section::make('结余前状态')
                                    ->description('当前系统状态')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('current_capital_confirm')
                                            ->label('当前本金')
                                            ->content(fn () => new HtmlString('<span class="font-bold text-lg">' . 
                                                             number_format($preview['current_capital'], 2) . ' HKD</span>')),
                                        
                                        \Filament\Forms\Components\Placeholder::make('current_hkd_balance_confirm')
                                            ->label('当前港币结余')
                                            ->content(fn () => new HtmlString('<span class="font-bold text-lg">' . 
                                                             number_format($preview['current_hkd_balance'], 2) . ' HKD</span>')),
                                        
                                        \Filament\Forms\Components\Placeholder::make('current_rmb_balance_confirm')
                                            ->label('人民币余额')
                                            ->content(fn () => new HtmlString('<span class="font-bold text-lg">' . 
                                                             number_format($preview['rmb_balance_total'], 2) . ' CNY</span>')),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                                
                                \Filament\Forms\Components\Section::make('结余计算')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('settlement_rate_confirm')
                                            ->label('结余汇率')
                                            ->content(fn () => new HtmlString('<span class="font-semibold text-lg">' . 
                                                             number_format($preview['settlement_rate'], 3) . '</span>')),
                                        
                                        \Filament\Forms\Components\Placeholder::make('outgoing_profit_confirm')
                                            ->label('出账利润（未扣除支出）')
                                            ->content(function () use ($preview) {
                                                $profit = $preview['outgoing_profit'];
                                                $color = $profit >= 0 ? 'success' : 'danger';
                                                return new HtmlString("<span class='text-{$color}-600 font-bold text-lg'>" . 
                                                       number_format($profit, 0) . ' HKD</span>');
                                            }),
                                        
                                        \Filament\Forms\Components\Placeholder::make('instant_profit_confirm')
                                            ->label('即时买断利润（未扣除支出）')
                                            ->content(function () use ($preview) {
                                                $profit = $preview['instant_profit'];
                                                $color = $profit >= 0 ? 'success' : 'danger';
                                                return new HtmlString("<span class='text-{$color}-600 font-bold text-lg'>" . 
                                                       number_format($profit, 0) . ' HKD</span>');
                                            }),
                                        
                                        \Filament\Forms\Components\Placeholder::make('total_expenses_display')
                                            ->label('其他支出合计')
                                            ->content(fn () => new HtmlString('<span class="text-danger-600 font-bold text-lg">- ' . 
                                                             number_format($totalExpenses, 2) . ' HKD</span>')),
                                        
                                        \Filament\Forms\Components\Placeholder::make('final_profit_display')
                                            ->label('最终利润（扣除支出后）')
                                            ->content(function () use ($finalProfit) {
                                                $color = $finalProfit >= 0 ? 'success' : 'danger';
                                                return new HtmlString("<span class='text-{$color}-600 font-bold text-xl'>" . 
                                                       number_format($finalProfit, 2) . ' HKD</span>');
                                            }),
                                    ])
                                    ->columns(5)
                                    ->columnSpanFull(),
                                
                                \Filament\Forms\Components\Section::make('结余后状态')
                                    ->description('执行结余后的预期状态')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('final_capital_display')
                                            ->label('结余后本金')
                                            ->content(fn () => new HtmlString('<span class="text-primary-600 font-bold text-xl">' . 
                                                             number_format($finalCapital, 2) . ' HKD</span>')),
                                        
                                        \Filament\Forms\Components\Placeholder::make('final_hkd_balance_display')
                                            ->label('结余后港币')
                                            ->content(function () use ($preview) {
                                                return new HtmlString('<span class="text-primary-600 font-bold text-xl">' . 
                                                       number_format($preview['expected_new_hkd_balance'], 2) . ' HKD</span>');
                                            }),
                                        
                                        \Filament\Forms\Components\Placeholder::make('final_rmb_balance_display')
                                            ->label('结余后人民币')
                                            ->content(function () use ($preview) {
                                                return new HtmlString('<span class="text-primary-600 font-bold text-xl">' . 
                                                       number_format($preview['rmb_balance_total'], 2) . ' CNY</span>');
                                            })
                                            ->helperText('人民币余额不受结余影响'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                                
                                \Filament\Forms\Components\Section::make('支出明细')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('expenses_detail')
                                            ->label('')
                                            ->content(function () use ($expenses) {
                                                if (empty($expenses)) {
                                                    return '无其他支出';
                                                }
                                                
                                                $html = '<div class="space-y-2">';
                                                foreach ($expenses as $expense) {
                                                    $html .= '<div class="flex justify-between items-center border-b pb-2">';
                                                    $html .= '<span class="font-medium">' . htmlspecialchars($expense['item_name']) . '</span>';
                                                    $html .= '<span class="text-gray-600">' . number_format($expense['amount'], 2) . ' HKD</span>';
                                                    $html .= '</div>';
                                                }
                                                $html .= '</div>';
                                                return new HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(fn () => empty($expenses)),
                                
                                // 日期选择 - 放在安全确认之前
                                \Filament\Forms\Components\Section::make('')
                                    ->schema([
                                        \Filament\Forms\Components\Placeholder::make('date_warning')
                                            ->label('')
                                            ->content(new HtmlString(
                                                '<div class="rounded-lg p-4 mb-4" style="background-color: ' . 
                                                ($recommendation['has_today'] ? '#FEF3C7; border: 2px solid #F59E0B;' : '#D1FAE5; border: 2px solid #10B981;') . '">' .
                                                '<div class="flex items-start gap-3">' .
                                                '<div class="text-2xl">' . ($recommendation['has_today'] ? '⚠️' : '✅') . '</div>' .
                                                '<div>' .
                                                '<div class="font-bold text-lg mb-1" style="color: ' . ($recommendation['has_today'] ? '#B45309' : '#065F46') . ';">' .
                                                ($recommendation['has_today'] ? '警告：今日已有结余记录' : '提示：今日尚未结余') .
                                                '</div>' .
                                                '<div class="text-sm" style="color: ' . ($recommendation['has_today'] ? '#92400E' : '#064E3B') . ';">' .
                                                ($recommendation['has_today'] ? $recommendation['message'] . '，请从下方选择可用日期' : '今日可以结余，请从下方选择可用的记录日期') .
                                                '</div>' .
                                                '<div class="text-xs mt-2 flex items-center gap-1" style="color: #6B7280;">' .
                                                '<span>💡</span>' .
                                                '<span>已有结余记录的日期将显示为灰色且无法选择</span>' .
                                                '</div>' .
                                                '</div>' .
                                                '</div>' .
                                                '</div>'
                                            )),
                                        
                                        \Filament\Forms\Components\DatePicker::make('settlement_date')
                                            ->label('📅 选择结余日期')
                                            ->required()
                                            ->default(null)
                                            ->minDate(now())
                                            ->native(false)
                                            ->displayFormat('Y年m月d日')
                                            ->placeholder('请选择可用日期')
                                            ->helperText('灰色日期表示已被占用，选择可用日期后才能输入确认密码')
                                            ->disabledDates($usedDates)
                                            ->validationMessages([
                                                'required' => '请选择结余日期',
                                                'after_or_equal' => '该日期不可用，请选择其他日期',
                                            ])
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) use ($usedDates) {
                                                // 检查选择的日期是否在禁用列表中
                                                if ($state && in_array($state, $usedDates)) {
                                                    Notification::make()
                                                        ->title('日期不可用')
                                                        ->warning()
                                                        ->body('该日期已有结余记录，请选择其他日期')
                                                        ->send();
                                                    $set('settlement_date', null);
                                                }
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                                
                                \Filament\Forms\Components\Section::make('安全确认')
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('password')
                                            ->label('确认密码')
                                            ->password()
                                            ->required()
                                            ->revealable()
                                            ->helperText('请输入管理员密码以确认执行结余操作')
                                            ->validationMessages([
                                                'required' => '请输入确认密码',
                                            ])
                                            ->columnSpanFull(),
                                    ])
                                    ->hidden(fn (callable $get) => !$get('settlement_date'))
                                    ->description('已选择日期，请输入密码确认结余'),
                            ];
                        }),
                ])
                ->action(function (array $data, SettlementService $settlementService, Actions\Action $action) {
                    try {
                        // 获取当前登录用户ID和类型
                        $userId = auth()->id();
                        $userType = 'admin'; // 后台管理界面只有管理员能访问
                        
                        $settlement = $settlementService->execute(
                            $data['password'],
                            $data['expenses'] ?? [],
                            $data['notes'] ?? null,
                            $userId,
                            $userType,
                            $data['settlement_date'] ?? null  // 传入选择的日期
                        );
                        
                        Notification::make()
                            ->title('结余成功')
                            ->success()
                            ->body(sprintf(
                                "结余序号 #%s，出账利润: %s HKD，即时买断利润: %s HKD，总利润: %s HKD",
                                $settlement->sequence_number,
                                number_format($settlement->outgoing_profit, 0),
                                number_format($settlement->instant_profit, 0),
                                number_format($settlement->profit, 0)
                            ))
                            ->send();
                        
                        // 刷新页面
                        return redirect()->route('filament.admin.resources.settlements.view', ['record' => $settlement]);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('结余失败')
                            ->danger()
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                        
                        // 阻止关闭模态框,保留用户输入的数据
                        $action->halt();
                    }
                })
                ->modalWidth('7xl')
                ->modalSubmitActionLabel('确认执行结余')
                ->modalCancelActionLabel('取消')
                ->before(function (SettlementService $settlementService, Actions\Action $action) {
                    // 检查是否有未结余的交易
                    $preview = $settlementService->getPreview();
                    
                    if (!$preview['can_settle']) {
                        Notification::make()
                            ->title('无法执行结余')
                            ->warning()
                            ->body('当前没有未结余的交易，无法执行结余操作')
                            ->send();
                        
                        $action->halt();
                    }
                }),
        ];
    }
}

