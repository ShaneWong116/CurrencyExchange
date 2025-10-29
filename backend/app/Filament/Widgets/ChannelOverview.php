<?php

namespace App\Filament\Widgets;

use App\Models\Channel;
use App\Models\Transaction;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ChannelOverview extends BaseWidget
{
    protected static ?string $heading = '渠道汇总';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    // 接收父页面传递的 location filter
    public ?string $locationFilter = 'all';

    protected $listeners = [
        'locationFilterChanged' => 'updateLocationFilter',
        '$refresh' => '$refresh',
    ];

    public function updateLocationFilter($locationId): void
    {
        $this->locationFilter = $locationId;
        // 强制立即刷新表格
        $this->resetTable();
    }

    protected function getLocationId(): ?int
    {
        return $this->locationFilter === 'all' ? null : (int) $this->locationFilter;
    }

    protected function getTableQuery(): Builder
    {
        return Channel::query()->where('status', 'active');
    }

    public function table(Table $table): Table
    {
        $locationId = $this->getLocationId();

        return $table
            ->query($this->getTableQuery())
            ->poll(null)  // 禁用轮询,避免延迟
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('支付渠道')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank' => 'success',
                        'ewallet' => 'warning',
                        'cash' => 'info',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank' => '银行',
                        'ewallet' => '电子钱包',
                        'cash' => '现金',
                        'other' => '其他',
                        default => $state,
                    }),
                    
                Tables\Columns\TextColumn::make('today_transactions_count')
                    ->label('交易笔数')
                    ->state(function (Channel $record) use ($locationId): int {
                        if ($locationId) {
                            // 按地点筛选 - 只统计未结算的交易
                            return Transaction::where('channel_id', $record->id)
                                ->where('location_id', $locationId)
                                ->where('settlement_status', 'unsettled')
                                ->count();
                        } else {
                            // 总览时从统计表读取
                            $stats = \App\Models\CurrentStatistic::getChannelStats($record->id);
                            return $stats['transaction_count'];
                        }
                    }),
                    
                Tables\Columns\TextColumn::make('today_rmb_income')
                    ->label('人民币入账')
                    ->state(function (Channel $record) use ($locationId): string {
                        if ($locationId) {
                            // 按地点筛选
                            $baseQuery = Transaction::where('channel_id', $record->id)
                                ->where('location_id', $locationId)
                                ->where('settlement_status', 'unsettled');
                            
                            $income = (clone $baseQuery)->where('type', 'income')->sum('rmb_amount');
                            $instantBuyout = (clone $baseQuery)->where('type', 'instant_buyout')->sum('rmb_amount');
                            $amount = $income + $instantBuyout;
                        } else {
                            // 从统计表读取
                            $stats = \App\Models\CurrentStatistic::getChannelStats($record->id);
                            
                            // 即时买断渠道：入账和出账显示相同金额
                            if ($record->name === '即时买断' || $record->category === 'instant_buyout') {
                                $amount = $stats['rmb_income'] + $stats['rmb_outcome'] + $stats['rmb_instant_buyout'];
                            } else {
                                $amount = $stats['rmb_income'];
                            }
                        }
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('today_rmb_outcome')
                    ->label('人民币出账')
                    ->state(function (Channel $record) use ($locationId): string {
                        if ($locationId) {
                            // 按地点筛选
                            $baseQuery = Transaction::where('channel_id', $record->id)
                                ->where('location_id', $locationId)
                                ->where('settlement_status', 'unsettled');
                            
                            $outcome = (clone $baseQuery)->where('type', 'outcome')->sum('rmb_amount');
                            $instantBuyout = (clone $baseQuery)->where('type', 'instant_buyout')->sum('rmb_amount');
                            $amount = $outcome + $instantBuyout;
                        } else {
                            // 从统计表读取
                            $stats = \App\Models\CurrentStatistic::getChannelStats($record->id);
                            
                            // 即时买断渠道：入账和出账显示相同金额
                            if ($record->name === '即时买断' || $record->category === 'instant_buyout') {
                                $amount = $stats['rmb_income'] + $stats['rmb_outcome'] + $stats['rmb_instant_buyout'];
                            } else {
                                $amount = $stats['rmb_outcome'];
                            }
                        }
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('today_hkd_income')
                    ->label('港币入账')
                    ->state(function (Channel $record) use ($locationId): string {
                        if ($locationId) {
                            // 按地点筛选（港币方向相反）
                            $baseQuery = Transaction::where('channel_id', $record->id)
                                ->where('location_id', $locationId)
                                ->where('settlement_status', 'unsettled');
                            
                            $outcome = (clone $baseQuery)->where('type', 'outcome')->sum('hkd_amount');
                            $instantBuyout = (clone $baseQuery)->where('type', 'instant_buyout')->sum('hkd_amount');
                            $amount = $outcome + $instantBuyout;
                        } else {
                            // 从统计表读取
                            $stats = \App\Models\CurrentStatistic::getChannelStats($record->id);
                            
                            // 即时买断渠道：入账和出账显示相同金额
                            if ($record->name === '即时买断' || $record->category === 'instant_buyout') {
                                $amount = $stats['hkd_income'] + $stats['hkd_outcome'] + $stats['hkd_instant_buyout'];
                            } else {
                                // 普通渠道：港币方向相反，入账按出账类型统计
                                $amount = $stats['hkd_outcome'];
                            }
                        }
                        return 'HK$' . number_format($amount, 2);
                    })
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('today_hkd_outcome')
                    ->label('港币出账')
                    ->state(function (Channel $record) use ($locationId): string {
                        if ($locationId) {
                            // 按地点筛选（港币方向相反）
                            $baseQuery = Transaction::where('channel_id', $record->id)
                                ->where('location_id', $locationId)
                                ->where('settlement_status', 'unsettled');
                            
                            $income = (clone $baseQuery)->where('type', 'income')->sum('hkd_amount');
                            $instantBuyout = (clone $baseQuery)->where('type', 'instant_buyout')->sum('hkd_amount');
                            $amount = $income + $instantBuyout;
                        } else {
                            // 从统计表读取
                            $stats = \App\Models\CurrentStatistic::getChannelStats($record->id);
                            
                            // 即时买断渠道：入账和出账显示相同金额
                            if ($record->name === '即时买断' || $record->category === 'instant_buyout') {
                                $amount = $stats['hkd_income'] + $stats['hkd_outcome'] + $stats['hkd_instant_buyout'];
                            } else {
                                // 普通渠道：港币方向相反，出账按入账类型统计
                                $amount = $stats['hkd_income'];
                            }
                        }
                        return 'HK$' . number_format($amount, 2);
                    })
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('rmb_balance')
                    ->label('人民币余额')
                    ->state(function (Channel $record): string {
                        return '¥' . number_format($record->getRmbBalance(), 2);
                    })
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('hkd_balance')
                    ->label('港币余额')
                    ->state(function (Channel $record): string {
                        return 'HK$' . number_format($record->getHkdBalance(), 2);
                    })
                    ->color('info'),
            ])
            ->defaultSort('today_transactions_count', 'desc');
    }
}