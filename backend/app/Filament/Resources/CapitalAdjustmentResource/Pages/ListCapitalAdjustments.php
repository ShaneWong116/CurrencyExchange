<?php

namespace App\Filament\Resources\CapitalAdjustmentResource\Pages;

use App\Filament\Resources\CapitalAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\CapitalAdjustment;

class ListCapitalAdjustments extends ListRecords
{
    protected static string $resource = CapitalAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('调整本金'),
        ];
    }
    
    public function getTitle(): string
    {
        return '系统本金管理';
    }
    
    public function getHeading(): string
    {
        return '系统本金管理';
    }
}

