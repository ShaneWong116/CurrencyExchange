<?php

namespace App\Filament\Resources\SettlementResource\Pages;

use App\Filament\Resources\SettlementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSettlement extends CreateRecord
{
    protected static string $resource = SettlementResource::class;
    
    // 此页面不使用，通过 ListSettlements 的 Action 执行结余
}

