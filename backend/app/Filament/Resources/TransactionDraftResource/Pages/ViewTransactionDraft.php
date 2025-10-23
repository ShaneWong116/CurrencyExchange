<?php

namespace App\Filament\Resources\TransactionDraftResource\Pages;

use App\Filament\Resources\TransactionDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransactionDraft extends ViewRecord
{
    protected static string $resource = TransactionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
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
}
