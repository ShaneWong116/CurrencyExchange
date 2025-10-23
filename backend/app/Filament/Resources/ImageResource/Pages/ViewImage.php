<?php

namespace App\Filament\Resources\ImageResource\Pages;

use App\Filament\Resources\ImageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewImage extends ViewRecord
{
    protected static string $resource = ImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('下载原图')
                ->icon('heroicon-m-arrow-down-tray')
                ->action(function () {
                    $content = base64_decode($this->record->file_content);
                    return response($content)
                        ->header('Content-Type', $this->record->mime_type)
                        ->header('Content-Disposition', 'attachment; filename="' . $this->record->original_name . '"');
                }),
        ];
    }
}
