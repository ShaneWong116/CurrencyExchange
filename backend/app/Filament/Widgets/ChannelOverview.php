<?php

namespace App\Filament\Widgets;

use App\Models\Channel;
use App\Models\Transaction;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Tables\Table;

class ChannelOverview extends BaseWidget
{
    protected static ?string $heading = '今日渠道汇总';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Channel::query()->where('status', 'active');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll('10s')
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
                    ->state(function (Channel $record): int {
                        return Transaction::where('channel_id', $record->id)
                            ->whereDate('created_at', today())
                            ->count();
                    }),
                    
                Tables\Columns\TextColumn::make('today_rmb_income')
                    ->label('人民币入账')
                    ->state(function (Channel $record): string {
                        $amount = Transaction::where('channel_id', $record->id)
                            ->where('type', 'income')
                            ->whereDate('created_at', today())
                            ->sum('rmb_amount');
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('today_rmb_outcome')
                    ->label('人民币出账')
                    ->state(function (Channel $record): string {
                        $amount = Transaction::where('channel_id', $record->id)
                            ->where('type', 'outcome')
                            ->whereDate('created_at', today())
                            ->sum('rmb_amount');
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('today_hkd_income')
                    ->label('港币入账')
                    ->state(function (Channel $record): string {
                        // 港币方向相反：入账按出账类型统计
                        $amount = Transaction::where('channel_id', $record->id)
                            ->where('type', 'outcome')
                            ->whereDate('created_at', today())
                            ->sum('hkd_amount');
                        return 'HK$' . number_format($amount, 2);
                    })
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('today_hkd_outcome')
                    ->label('港币出账')
                    ->state(function (Channel $record): string {
                        // 港币方向相反：出账按入账类型统计
                        $amount = Transaction::where('channel_id', $record->id)
                            ->where('type', 'income')
                            ->whereDate('created_at', today())
                            ->sum('hkd_amount');
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