<?php

namespace App\Filament\Resources\FieldUserResource\Pages;

use App\Filament\Resources\FieldUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFieldUser extends ViewRecord
{
    protected static string $resource = FieldUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}


