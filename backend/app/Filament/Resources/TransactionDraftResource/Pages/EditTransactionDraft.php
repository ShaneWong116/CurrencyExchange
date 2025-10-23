<?php

namespace App\Filament\Resources\TransactionDraftResource\Pages;

use App\Filament\Resources\TransactionDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransactionDraft extends EditRecord
{
    protected static string $resource = TransactionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('submit')
                ->label('提交为正式交易')
                ->icon('heroicon-m-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $transaction = $this->record->convertToTransaction();
                    $this->record->delete();
                    
                    $this->redirect(TransactionDraftResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['last_modified'] = now();
        
        return $data;
    }
}
