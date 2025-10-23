<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = '财务管理仪表盘';
    protected static ?string $navigationLabel = '仪表盘';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\PrimaryNetInflow::class,
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\ChannelOverview::class,
        ];
    }
}