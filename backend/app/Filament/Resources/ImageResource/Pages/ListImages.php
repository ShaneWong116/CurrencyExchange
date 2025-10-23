<?php

namespace App\Filament\Resources\ImageResource\Pages;

use App\Filament\Resources\ImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImages extends ListRecords
{
    protected static string $resource = ImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cleanup_all_orphaned')
                ->label('清理所有孤立文件')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('确认清理所有孤立文件')
                ->modalDescription('此操作将删除所有没有关联交易或草稿的图片文件，无法恢复。')
                ->action(function () {
                    $count = \App\Models\Image::whereNull('transaction_id')
                        ->whereNull('draft_id')
                        ->count();
                        
                    \App\Models\Image::whereNull('transaction_id')
                        ->whereNull('draft_id')
                        ->delete();
                    
                    \Filament\Notifications\Notification::make()
                        ->title("已清理 {$count} 个孤立文件")
                        ->success()
                        ->send();
                })
                ->visible(fn () => ($u = auth()->user()) instanceof \App\Models\User && $u->role === 'admin'),
        ];
    }
}
