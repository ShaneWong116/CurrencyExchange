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
            Actions\Action::make('current_capital')
                ->label(fn () => '💰 当前系统本金: HK$ ' . number_format(CapitalAdjustment::getCurrentCapital(), 2))
                ->color('success')
                ->disabled()
                ->extraAttributes(['class' => 'text-lg font-bold']),
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

