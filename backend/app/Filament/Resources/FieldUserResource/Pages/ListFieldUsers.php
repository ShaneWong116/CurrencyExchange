<?php

namespace App\Filament\Resources\FieldUserResource\Pages;

use App\Filament\Resources\FieldUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFieldUsers extends ListRecords
{
    protected static string $resource = FieldUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


