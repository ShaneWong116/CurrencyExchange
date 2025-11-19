<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // 如果交易已结算，禁止编辑并重定向
        if ($this->record->isSettled()) {
            Notification::make()
                ->warning()
                ->title('无法编辑')
                ->body('不能编辑已结算的交易记录。如需修改，请先撤销相关的结算记录。')
                ->persistent()
                ->send();
            
            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->hidden(fn (): bool => $this->record->isSettled()),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
