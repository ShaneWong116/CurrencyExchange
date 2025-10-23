<?php

namespace App\Filament\Widgets;

use App\Models\Location;
use App\Models\Transaction;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Tables\Table;

class LocationOverview extends BaseWidget
{
    protected static ?string $heading = '今日地点汇总';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return Location::query()->where('status', 'active');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('地点')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('today_transactions_count')
                    ->label('交易笔数')
                    ->state(function (Location $record): int {
                        return Transaction::where('location_id', $record->id)
                            ->whereDate('created_at', today())
                            ->count();
                    }),

                Tables\Columns\TextColumn::make('today_rmb_income')
                    ->label('人民币入账')
                    ->state(function (Location $record): string {
                        $amount = Transaction::where('location_id', $record->id)
                            ->where('type', 'income')
                            ->whereDate('created_at', today())
                            ->sum('rmb_amount');
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('danger'),

                Tables\Columns\TextColumn::make('today_rmb_outcome')
                    ->label('人民币出账')
                    ->state(function (Location $record): string {
                        $amount = Transaction::where('location_id', $record->id)
                            ->where('type', 'outcome')
                            ->whereDate('created_at', today())
                            ->sum('rmb_amount');
                        return '¥' . number_format($amount, 2);
                    })
                    ->color('success'),

                Tables\Columns\TextColumn::make('today_hkd_income')
                    ->label('港币入账')
                    ->state(function (Location $record): string {
                        // 港币方向相反：入账按出账类型统计
                        $amount = Transaction::where('location_id', $record->id)
                            ->where('type', 'outcome')
                            ->whereDate('created_at', today())
                            ->sum('hkd_amount');
                        return 'HK$' . number_format($amount, 2);
                    })
                    ->color('danger'),

                Tables\Columns\TextColumn::make('today_hkd_outcome')
                    ->label('港币出账')
                    ->state(function (Location $record): string {
                        // 港币方向相反：出账按入账类型统计
                        $amount = Transaction::where('location_id', $record->id)
                            ->where('type', 'income')
                            ->whereDate('created_at', today())
                            ->sum('hkd_amount');
                        return 'HK$' . number_format($amount, 2);
                    })
                    ->color('success'),
            ])
            ->defaultSort('today_transactions_count', 'desc');
    }
}


