<?php

namespace App\Filament\Resources\TransactionDraftResource\Pages;

use App\Filament\Resources\TransactionDraftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionDraft extends CreateRecord
{
    protected static string $resource = TransactionDraftResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['last_modified'] = now();
        
        return $data;
    }
}
