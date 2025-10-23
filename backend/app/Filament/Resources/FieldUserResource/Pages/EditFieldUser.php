<?php

namespace App\Filament\Resources\FieldUserResource\Pages;

use App\Filament\Resources\FieldUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFieldUser extends EditRecord
{
    protected static string $resource = FieldUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}


