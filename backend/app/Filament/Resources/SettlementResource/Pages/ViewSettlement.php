<?php

namespace App\Filament\Resources\SettlementResource\Pages;

use App\Filament\Resources\SettlementResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSettlement extends ViewRecord
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('返回列表')
                ->icon('heroicon-o-arrow-left')
                ->url(SettlementResource::getUrl('index')),
        ];
    }
}

