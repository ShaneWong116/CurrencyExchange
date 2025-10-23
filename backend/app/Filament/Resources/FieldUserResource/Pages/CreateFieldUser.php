<?php

namespace App\Filament\Resources\FieldUserResource\Pages;

use App\Filament\Resources\FieldUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFieldUser extends CreateRecord
{
    protected static string $resource = FieldUserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}


