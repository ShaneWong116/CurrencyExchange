<?php

namespace App\Filament\Exports;

use App\Models\Transaction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('user.name')->label('外勤人员'),
            ExportColumn::make('type')
                ->label('交易类型')
                ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                    'income' => '入账',
                    'outcome' => '出账',
                    'instant_buyout' => '即时买断',
                    'exchange' => '兑换',
                    default => $state,
                }),
            ExportColumn::make('rmb_amount')->label('人民币'),
            ExportColumn::make('hkd_amount')->label('港币'),
            ExportColumn::make('exchange_rate')->label('汇率'),
            ExportColumn::make('instant_rate')->label('即时买断汇率'),
            ExportColumn::make('channel.name')->label('支付渠道'),
            ExportColumn::make('location.name')->label('地点'),
            ExportColumn::make('status')
                ->label('状态')
                ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                    'pending' => '处理中',
                    'success' => '成功',
                    'failed' => '失败',
                    default => $state,
                }),
            ExportColumn::make('created_at')
                ->label('创建时间')
                ->formatStateUsing(fn ($state): ?string => $state ? $state->format('Y-m-d H:i') : null),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return '交易记录导出完成，点击下载导出文件。';
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
        return '交易记录导出-' . now()->format('Ymd-His');
    }
}


