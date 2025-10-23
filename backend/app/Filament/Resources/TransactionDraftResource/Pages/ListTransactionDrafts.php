<?php

namespace App\Filament\Resources\TransactionDraftResource\Pages;

use App\Filament\Resources\TransactionDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactionDrafts extends ListRecords
{
    protected static string $resource = TransactionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('cleanup_all')
                ->label('清理所有草稿')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('确认清理所有草稿')
                ->modalDescription('此操作将删除所有草稿记录，无法恢复。')
                ->action(function () {
                    $count = \App\Models\TransactionDraft::count();
                    \App\Models\TransactionDraft::truncate();
                    
                    \Filament\Notifications\Notification::make()
                        ->title("已清理 {$count} 条草稿记录")
                        ->success()
                        ->send();
                })
                ->visible(fn() => auth()->user()->isAdmin()),
        ];
    }
}
