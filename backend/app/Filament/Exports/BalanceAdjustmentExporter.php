<?php

namespace App\Filament\Exports;

use App\Models\BalanceAdjustment;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class BalanceAdjustmentExporter extends Exporter
{
    protected static ?string $model = BalanceAdjustment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('channel.name')->label('支付渠道'),
            ExportColumn::make('currency')
                ->label('货币')
                ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                    'RMB' => '人民币',
                    'HKD' => '港币',
                    default => $state,
                }),
            ExportColumn::make('before_amount')->label('调整前'),
            ExportColumn::make('adjustment_amount')->label('调整金额'),
            ExportColumn::make('after_amount')->label('调整后'),
            ExportColumn::make('type')
                ->label('类型')
                ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                    'manual' => '手动',
                    'system' => '系统',
                    default => $state,
                }),
            ExportColumn::make('user.username')->label('操作人'),
            ExportColumn::make('reason')->label('调整原因'),
            ExportColumn::make('created_at')
                ->label('调整时间')
                ->formatStateUsing(fn ($state): ?string => $state ? $state->format('Y-m-d H:i') : null),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return '余额调整导出完成，点击下载导出文件。';
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
            ExportFormat::Csv,
        ];
    }

    public function getFileName(Export $export): string
    {
        return '余额调整导出-' . now()->format('Ymd-His');
    }
}


